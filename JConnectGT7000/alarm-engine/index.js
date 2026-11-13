const config = require("./config");
const db = require("./db");
const NotificationRouter = require("./NotificationRouter");
const express = require("express");
const fs = require("fs");
const path = require("path");
const { buildAlarmTemplateVars, renderNotificationTemplate } = require("./templateContext");

/** Align with gen3/web DEFAULT_NOTIFICATION_MESSAGE_TEMPLATE when DB/template field is empty. */
const DEFAULT_NOTIFICATION_MESSAGE_TEMPLATE =
  "{{device.name}} · {{metric.label}} = {{metric.value}}";

function channelMessageTemplateOrDefault(raw) {
  const t = raw != null ? String(raw).trim() : "";
  return t || DEFAULT_NOTIFICATION_MESSAGE_TEMPLATE;
}

const app = express();
app.use(express.json());

app.get("/health", (_req, res) => {
  res.json({ ok: true, service: "alarm-engine" });
});

async function tryLogDispatch(alarmLogId, channelId, status, errorText) {
  try {
    await db.query(
      `INSERT INTO "alarm_log_dispatch" ("alarm_log_id", "channel_id", status, "sent_at", error) VALUES ($1, $2, $3, NOW(), $4)`,
      [alarmLogId, channelId, status, errorText || null]
    );
  } catch (e) {
    console.warn("[ALARM WORKER] alarm_log_dispatch skip:", e.message);
  }
}

/**
 * POST /api/send-test
 * - Saved channel: body { channelId, message? } — loads row from notification_channels (requires save first).
 * - Dry-run (no DB row): body { type, config, message, ctx? } — same idea as gen3/backend POST /notification-channels/test-send.
 */
app.post("/api/send-test", async (req, res) => {
  try {
    const body = req.body || {};
    const { channelId, message, ctx } = body;
    const type = body.type != null ? String(body.type).trim() : "";
    const configPayload = body.config;

    const dryRun =
      (channelId == null || channelId === "") &&
      type &&
      configPayload &&
      typeof configPayload === "object" &&
      !Array.isArray(configPayload);

    if (dryRun) {
      const testMessage = String(message || "").trim();
      if (!testMessage) {
        return res.json({ success: false, error: "message is required for dry-run (no channelId)" });
      }
      const ctxSafe = ctx && typeof ctx === "object" && !Array.isArray(ctx) ? ctx : {};
      await NotificationRouter.send(
        { type, config: configPayload },
        testMessage,
        {
          device: String(ctxSafe.device ?? ""),
          label: String(ctxSafe.label ?? ""),
          value: String(ctxSafe.value ?? ""),
        }
      );
      console.log(`[TEST MESSAGE] Dry-run type=${type}`);
      return res.json({
        success: true,
        message: "Test message sent successfully (dry-run, not saved)",
        mode: "dry-run",
        type,
      });
    }

    if (!channelId) {
      return res.json({
        success: false,
        error:
          "Channel ID is required for a saved channel, or send { type, config, message } to test before save",
      });
    }

    const { rows } = await db.query(
      `SELECT id,
              name,
              type,
              is_active AS "isActive",
              message_template AS "messageTemplate",
              description,
              config,
              is_deleted AS "isDeleted"
         FROM notification_channels
        WHERE id = $1 AND is_active = true AND is_deleted = false`,
      [parseInt(channelId, 10)]
    );

    const channel = rows[0];
    if (!channel) {
      return res.json({ success: false, error: "Channel not found or inactive" });
    }

    const testMessage =
      String(message || "").trim() ||
      channelMessageTemplateOrDefault(channel.messageTemplate);

    await NotificationRouter.send(channel, testMessage, {
      device: "Test device",
      label: "Test metric",
      value: "0",
    });

    console.log(`[TEST MESSAGE] Sent to channel ${channelId}: ${testMessage}`);

    res.json({
      success: true,
      message: "Test message sent successfully",
      channel: channel.name,
      type: channel.type,
    });
  } catch (error) {
    console.error("[TEST MESSAGE ERROR]:", error.message);
    res.json({
      success: false,
      error: error.message,
    });
  }
});

const PORT = config.alarmWorkerPort;
app.listen(PORT, () => {
  console.log(`Alarm engine worker listening on port ${PORT}`);
});

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function resolveSnapshotFile(publicPath) {
  if (!publicPath) return null;
  const raw = String(publicPath).trim();
  if (!raw) return null;
  if (path.isAbsolute(raw) && fs.existsSync(raw)) return raw;

  const relative = raw.replace(/^\/media\/?/, "");
  const roots = [
    process.env.MEDIA_ROOT,
    "/var/www/jconnect/media",
    path.join(__dirname, "..", "media"),
  ].filter(Boolean);

  for (const root of roots) {
    const full = path.join(root, relative);
    if (fs.existsSync(full)) return full;
  }
  return null;
}

function deviceHasSnapshotBinding(configRaw, deviceId, metricKey) {
  let cfg = configRaw;
  if (typeof cfg === "string") {
    try {
      cfg = JSON.parse(cfg);
    } catch {
      return false;
    }
  }
  const rules = Array.isArray(cfg?.snapshot_triggers) ? cfg.snapshot_triggers : [];
  return rules.some((rule) => {
    if (!rule || rule.enable === false || rule.enable === "false") return false;
    return Number(rule.source_device_id) === Number(deviceId)
      && String(rule.source_metric_key || "") === String(metricKey);
  });
}

async function expectsSnapshot(deviceId, metricKey) {
  try {
    const { rows } = await db.query(
      `SELECT config FROM devices WHERE config::text LIKE '%snapshot_triggers%'`
    );
    return rows.some((row) => deviceHasSnapshotBinding(row.config, deviceId, metricKey));
  } catch (e) {
    console.warn("[ALARM WORKER] expectsSnapshot:", e.message);
    return false;
  }
}

async function waitForSnapshot(job) {
  const readPath = async () => {
    const { rows } = await db.query(
      `SELECT snapshot_path AS "snapshotPath",
              snapshot_camera_device_id AS "snapshotCameraDeviceId"
         FROM alarm_logs
        WHERE id = $1`,
      [job.id]
    );
    return rows[0] || {};
  };

  let row = await readPath();
  if (row.snapshotPath) return row;
  if (!(await expectsSnapshot(job.deviceId, job.metricKey))) return row;

  for (let i = 0; i < 12; i += 1) {
    await sleep(1000);
    row = await readPath();
    if (row.snapshotPath) return row;
  }
  console.warn(`[ALARM WORKER] snapshot not ready for alarm ${job.id} after wait`);
  return row;
}

function snapshotAttachments(snapshotPath) {
  const filePath = resolveSnapshotFile(snapshotPath);
  if (!filePath) {
    if (snapshotPath) {
      console.warn("[ALARM WORKER] snapshot file missing on disk:", snapshotPath);
    }
    return [];
  }
  return [{
    filename: path.basename(filePath),
    path: filePath,
    cid: "alarm-snapshot",
    contentType: "image/jpeg",
  }];
}

function publicSnapshotUrl(snapshotPath) {
  if (!snapshotPath) return null;
  const raw = String(snapshotPath).trim();
  if (/^https:\/\//i.test(raw)) return raw;
  const base = String(process.env.PUBLIC_BASE_URL || "https://ntsate.ihx.co.th").replace(/\/$/, "");
  const rel = raw.startsWith("/") ? raw : `/${raw}`;
  return `${base}${rel}`;
}

let isProcessing = false;

async function processQueue() {
  if (isProcessing) {
    console.log("[ALARM WORKER] Already processing, skipping this cycle");
    return;
  }

  isProcessing = true;

  try {
    const { rows: jobs } = await db.query(`
      SELECT
        l.id,
        l.device_id AS "deviceId",
        l.metric_key AS "metricKey",
        l.trigger_id AS "triggerId",
        l.value,
        l.message,
        l.severity,
        l.channels,
        l.created_at AS "createdAt",
        l.trigger_type AS "triggerType",
        l.duration_minutes AS "durationMinutes",
        d.name AS "deviceName"
      FROM alarm_logs l
      LEFT JOIN devices d ON d.id = l.device_id
      WHERE l.status = 'queued'
      ORDER BY l.created_at ASC
      LIMIT 10
    `);

    if (jobs.length === 0) {
      console.log("[ALARM WORKER] No queued jobs found");
      return;
    }

    console.log(`[ALARM WORKER] Found ${jobs.length} queued jobs`);

    for (const job of jobs) {
      console.log("[ALARM WORKER] Processing job:", job.id);

      try {
        const updateResult = await db.query(
          `UPDATE "alarm_logs" SET status = 'processing' WHERE id = $1 AND status = 'queued'`,
          [job.id]
        );

        if (updateResult.rowCount === 0) {
          console.log("[ALARM WORKER] Job already taken by another worker:", job.id);
          continue;
        }

        let channelIds = job.channels;
        if (channelIds == null) channelIds = [];
        else if (typeof channelIds === "string") {
          try {
            channelIds = JSON.parse(channelIds);
          } catch {
            channelIds = [];
          }
        }
        if (!Array.isArray(channelIds)) channelIds = [];

        console.log("[ALARM WORKER] Channel IDs:", channelIds);

        let allChannelsSent = channelIds.length > 0;
        const templateVars = await buildAlarmTemplateVars(job);

        for (const id of channelIds) {
          try {
            const { rows } = await db.query(
              `SELECT id,
                      name,
                      type,
                      is_active AS "isActive",
                      message_template AS "messageTemplate",
                      description,
                      config,
                      is_deleted AS "isDeleted"
                 FROM notification_channels
                WHERE id = $1 AND is_active = true AND is_deleted = false`,
              [parseInt(id, 10)]
            );
            const channel = rows[0];
            if (!channel) {
              console.log("[ALARM WORKER] Channel not found or inactive:", id);
              allChannelsSent = false;
              await tryLogDispatch(job.id, id, "failed", "Channel not found or inactive");
              continue;
            }

            console.log("[ALARM WORKER] Sending message to channel:", channel.name);
            const rendered = renderNotificationTemplate(
              channelMessageTemplateOrDefault(channel.messageTemplate),
              templateVars
            );
            const bodyText = rendered.trim() ? rendered : String(job.message || "");

            const snapshot = await waitForSnapshot(job);
            const extras = {
              attachments: snapshotAttachments(snapshot.snapshotPath),
              imageUrl: publicSnapshotUrl(snapshot.snapshotPath),
              snapshotPath: snapshot.snapshotPath,
            };
            if (extras.attachments.length) {
              console.log("[ALARM WORKER] attaching snapshot", extras.attachments[0].path);
            }
            if (extras.imageUrl) {
              console.log("[ALARM WORKER] snapshot URL", extras.imageUrl);
            }

            await NotificationRouter.send(channel, bodyText, {
              device: String(templateVars["device.name"] || ""),
              label: String(templateVars["metric.label"] || ""),
              value: String(templateVars["metric.value"] || ""),
            }, extras);

            await tryLogDispatch(job.id, id, "sent", null);

            console.log("[ALARM WORKER] Successfully sent to channel:", channel.name);

            await new Promise((r) => setTimeout(r, 1000));
          } catch (innerErr) {
            allChannelsSent = false;
            console.error("Channel dispatch error for channel", id, ":", innerErr.message);
            await tryLogDispatch(job.id, id, "failed", innerErr.message);
            await new Promise((r) => setTimeout(r, 2000));
          }
        }

        if (channelIds.length === 0) {
          allChannelsSent = true;
        }

        const finalStatus = allChannelsSent ? "sent" : "partial_failed";
        await db.query(
          `UPDATE alarm_logs SET status = $1, sent_at = NOW() WHERE id = $2`,
          [finalStatus, job.id]
        );

        console.log("[ALARM WORKER] Job completed with status:", finalStatus, "for job:", job.id);
      } catch (err) {
        console.error("Job error for job", job.id, ":", err.message);
        await db.query(
          `UPDATE alarm_logs SET status = 'failed', error = $1 WHERE id = $2`,
          [err.message, job.id]
        );
      }

      await new Promise((r) => setTimeout(r, 500));
    }
  } catch (err) {
    console.error("Queue processing error:", err);
  } finally {
    isProcessing = false;
  }
}

async function cleanupStuckJobs() {
  try {
    const result = await db.query(
      `UPDATE alarm_logs
          SET status = 'queued'
        WHERE status = 'processing'
          AND created_at < NOW() - INTERVAL '5 minutes'`
    );
    if (result.rowCount > 0) {
      console.log(`[ALARM WORKER] Cleaned up ${result.rowCount} stuck processing jobs`);
    }
  } catch (err) {
    console.error("Cleanup error:", err);
  }
}

cleanupStuckJobs();

setInterval(processQueue, 5000);
