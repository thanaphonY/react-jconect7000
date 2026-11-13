const axios = require("axios");

function substitutePlaceholders(str, { message, timestamp, device, label, value }) {
  if (typeof str !== "string") return str;
  return str
    .replaceAll("{{message}}", message)
    .replaceAll("{{timestamp}}", timestamp)
    .replaceAll("{{device}}", device)
    .replaceAll("{{label}}", label)
    .replaceAll("{{value}}", value);
}

/**
 * Gen3 wizard: url, method (GET|POST), headers (object), bodyTemplate (JSON string, optional).
 * Legacy Gen2: url only → POST { message, timestamp }.
 */
module.exports = {
  async send(config, message) {
    const url = config.url;
    if (!url) throw new Error("Webhook config missing URL");

    const method = String(config.method || "POST").toUpperCase();
    const timestamp = new Date().toISOString();
    const device = String(config._ctx_device ?? "");
    const label = String(config._ctx_label ?? "");
    const value = String(config._ctx_value ?? "");
    const subs = (s) => substitutePlaceholders(s, { message: String(message), timestamp, device, label, value });

    const baseHeaders = { ...(typeof config.headers === "object" && config.headers && !Array.isArray(config.headers) ? config.headers : {}) };
    const ctxKeys = ["_ctx_device", "_ctx_label", "_ctx_value"];
    for (const k of ctxKeys) delete baseHeaders[k];

    if (method === "GET") {
      const res = await axios.get(url, { headers: baseHeaders, timeout: 20000 });
      return res.data;
    }

    const bodyTpl = config.bodyTemplate != null ? String(config.bodyTemplate).trim() : "";
    let data;
    if (bodyTpl) {
      const raw = subs(bodyTpl);
      try {
        data = JSON.parse(raw);
      } catch {
        data = raw;
      }
    } else {
      data = { message: String(message), timestamp };
    }

    const headers = {
      "Content-Type": typeof data === "object" && data !== null ? "application/json" : "text/plain",
      ...baseHeaders,
    };

    const res = await axios.post(url, data, { headers, timeout: 30000 });
    return res.data;
  },
};
