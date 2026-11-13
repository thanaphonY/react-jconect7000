const nodemailer = require("nodemailer");

/** Normalize Gen2 keys (smtp_host, …) and Gen3 wizard (host, receivers, …). */
function normalizeConfig(config) {
  const c = { ...(config && typeof config === "object" ? config : {}) };
  c.smtp_host = c.smtp_host || c.host;
  c.smtp_port = Number(c.smtp_port ?? c.port ?? 587);
  c.smtp_user = c.smtp_user || c.username || null;
  c.smtp_pass = c.smtp_pass || c.password || null;
  c.to = c.to || c.receivers;
  c.subject = c.subject || "Alarm Notification";

  const sec = String(c.secure != null ? c.secure : "").toLowerCase();
  if (c.smtp_secure === undefined || c.smtp_secure === null || c.smtp_secure === "") {
    if (sec === "ssl" || c.smtp_port === 465) c.smtp_secure = true;
    else c.smtp_secure = false;
  }
  if (typeof c.smtp_secure === "string") c.smtp_secure = c.smtp_secure === "true";

  if (!c.from) {
    if (c.smtp_user && String(c.smtp_user).includes("@")) {
      c.from = c.sendername ? `"${c.sendername}" <${c.smtp_user}>` : c.smtp_user;
    } else if (c.to) {
      const first = String(c.to).split(",")[0].trim();
      c.from = c.sendername ? `"${c.sendername || "Alarm"}" <${first}>` : first;
    }
  }
  return c;
}

module.exports = {
  async send(config, message, extras = {}) {
    const c = normalizeConfig(config);
    if (!c.to || !c.from || !c.smtp_host) {
      throw new Error("Email config missing required fields: receivers (to), from (or username / first receiver), host (smtp_host)");
    }

    const sec = String(config.secure != null ? config.secure : "").toLowerCase();
    const requireTLS = sec === "tls" || sec === "starttls";

    const transportConfig = {
      host: c.smtp_host,
      port: c.smtp_port || 587,
      secure: Boolean(c.smtp_secure),
      requireTLS,
      tls: { rejectUnauthorized: false },
    };

    if (c.smtp_user && c.smtp_pass) {
      transportConfig.auth = { user: c.smtp_user, pass: c.smtp_pass };
    }

    const extra = extras && typeof extras === "object" ? extras : {};
    const attachments = Array.isArray(extra.attachments) ? extra.attachments : [];
    const text = String(message);
    const htmlParts = [
      `<pre style="font-family:ui-sans-serif,system-ui,sans-serif;white-space:pre-wrap;margin:0 0 16px">${escapeHtml(text)}</pre>`,
    ];
    const mailAttachments = attachments.map((item, index) => {
      const filename = item.filename || `snapshot-${index + 1}.jpg`;
      const cid = item.cid || (index === 0 ? "alarm-snapshot" : `alarm-snapshot-${index}`);
      htmlParts.push(
        `<p><img src="cid:${cid}" alt="Alarm snapshot" style="max-width:100%;height:auto;border-radius:8px" /></p>`
      );
      return {
        filename,
        path: item.path,
        contentType: item.contentType || "image/jpeg",
        cid,
      };
    });

    const mail = {
      from: c.from,
      to: c.to,
      subject: c.subject,
      text,
    };
    if (mailAttachments.length) {
      mail.html = htmlParts.join("");
      mail.attachments = mailAttachments;
    }

    const transporter = nodemailer.createTransport(transportConfig);
    const info = await transporter.sendMail(mail);
    return info;
  },
};

function escapeHtml(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
