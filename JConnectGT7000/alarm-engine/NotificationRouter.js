const LineOADriver = require("./drivers/LineOADriver");
const EmailDriver = require("./drivers/EmailDriver");
const WebhookDriver = require("./drivers/WebhookDriver");
const TelegramDriver = require("./drivers/TelegramDriver");
const DiscordDriver = require("./drivers/DiscordDriver");

function parseConfig(raw) {
  if (raw == null) return {};
  if (typeof raw === "object" && !Array.isArray(raw)) return { ...raw };
  if (typeof raw === "string") {
    try {
      return JSON.parse(raw);
    } catch {
      return {};
    }
  }
  return {};
}

module.exports = {
  /**
   * @param {object} channel row from notification_channels (pg)
   * @param {string} message rendered text to send
   * @param {{ device?: string, label?: string, value?: string }} [ctx] optional template context for webhook body
   * @param {{ attachments?: Array<{ filename?: string, path: string, cid?: string, contentType?: string }> }} [extras]
   */
  async send(channel, message, ctx = {}, extras = {}) {
    const type = String(channel.type || "");
    const config = parseConfig(channel.config);
    const merged = {
      ...config,
      _ctx_device: ctx.device ?? "",
      _ctx_label: ctx.label ?? "",
      _ctx_value: ctx.value != null ? String(ctx.value) : "",
    };

    switch (type) {
      case "line_oa":
      case "line":
        return await LineOADriver.send(config, message, extras);
      case "email":
        return await EmailDriver.send(config, message, extras);
      case "webhook":
        return await WebhookDriver.send(merged, message);
      case "telegram":
        return await TelegramDriver.send(config, message);
      case "discord":
        return await DiscordDriver.send(config, message);
      default:
        throw new Error("Unsupported channel type: " + type);
    }
  },
};
