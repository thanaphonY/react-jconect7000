const axios = require("axios");

/** Discord Incoming Webhook — body { content: string } */
module.exports = {
  async send(config, message) {
    const url = config.webhook_url || config.url;
    if (!url || typeof url !== "string") {
      throw new Error("Discord config requires webhook_url");
    }
    if (!url.includes("discord.com/api/webhooks")) {
      throw new Error("Discord webhook_url must be a discord.com/api/webhooks URL");
    }
    const text = String(message ?? "").slice(0, 2000);
    const res = await axios.post(
      url,
      { content: text },
      {
        headers: { "Content-Type": "application/json" },
        timeout: 20000,
        validateStatus: () => true,
      }
    );
    if (res.status < 200 || res.status >= 300) {
      throw new Error(`Discord webhook HTTP ${res.status}: ${typeof res.data === "string" ? res.data : JSON.stringify(res.data)}`);
    }
    return res.data;
  },
};
