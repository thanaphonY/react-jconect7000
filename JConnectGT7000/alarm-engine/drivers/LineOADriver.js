const axios = require("axios");
const path = require("path");

function lineApiErrorMessage(err) {
  if (!axios.isAxiosError(err) || !err.response) return err?.message || "LINE request failed";
  const status = err.response.status;
  const data = err.response.data;
  let detail = "";
  if (data && typeof data === "object" && data.message != null) detail = String(data.message);
  else if (typeof data === "string") detail = data;
  else if (data != null) try { detail = JSON.stringify(data); } catch { detail = ""; }
  const suffix = detail ? `: ${detail}` : "";
  if (status === 401) {
    const lineMsg = detail.replace(/\s+/g, " ").trim().replace(/\.+$/, "");
    const hint =
      "Re-issue or paste the Channel access token from Messaging API (not Channel secret); copy the full string with no extra spaces. " +
      "If you issued a new long-lived token in the console, the previous long-lived token is invalidated immediately.";
    return lineMsg ? `LINE 401: ${lineMsg}. ${hint}` : `LINE 401: Invalid or expired bearer token. ${hint}`;
  }
  return `LINE API ${status}${suffix}`;
}

function publicBaseUrl() {
  return String(process.env.PUBLIC_BASE_URL || "https://ntsate.ihx.co.th").replace(/\/$/, "");
}

/** Build HTTPS URL LINE can fetch (Cloudflare Tunnel). */
function resolveImageUrl(extras) {
  if (extras && extras.imageUrl && /^https:\/\//i.test(String(extras.imageUrl))) {
    return String(extras.imageUrl);
  }
  const attachments = Array.isArray(extras?.attachments) ? extras.attachments : [];
  const filePath = attachments[0]?.path || extras?.snapshotPath || "";
  if (!filePath) return null;
  if (/^https:\/\//i.test(String(filePath))) return String(filePath);
  const rel = String(filePath).includes("/media/")
    ? "/media/" + String(filePath).split("/media/").pop()
    : `/media/snapshots/${path.basename(String(filePath))}`;
  return `${publicBaseUrl()}${rel.startsWith("/") ? rel : `/${rel}`}`;
}

module.exports = {
  async send(config, message, extras = {}) {
    const rawToken = String(config.access_token ?? "");
    const token = rawToken.trim();
    const to = String(config.to ?? "").trim();
    if (!token || !to) {
      throw new Error("LINE OA config requires 'access_token' and 'to'");
    }

    const dbg = process.env.LINE_DRIVER_DEBUG === "1" || process.env.LINE_DRIVER_DEBUG === "true";
    if (dbg) {
      console.log("[LineOADriver] LINE_DRIVER_DEBUG", {
        tokenLength: token.length,
        toLength: to.length,
      });
    }

    const headers = {
      Authorization: `Bearer ${token}`,
      "Content-Type": "application/json",
    };

    const imageUrl = resolveImageUrl(extras);
    const text = String(message || "");
    const messages = [];

    if (text) {
      messages.push({ type: "text", text });
    }

    if (imageUrl) {
      console.log("[LineOADriver] image URL:", imageUrl);
      messages.push({
        type: "image",
        originalContentUrl: imageUrl,
        previewImageUrl: imageUrl,
      });
    }

    if (!messages.length) {
      messages.push({ type: "text", text: "(empty message)" });
    }

    try {
      const response = await axios.post(
        "https://api.line.me/v2/bot/message/push",
        { to, messages },
        { headers }
      );
      return response.data;
    } catch (e) {
      throw new Error(lineApiErrorMessage(e));
    }
  },
};
