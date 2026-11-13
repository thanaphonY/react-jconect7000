
const axios = require('axios');

module.exports = {
  async send(config, message) {
    if (!config.access_token || !config.to) {
      throw new Error("LINE OA config requires 'access_token' and 'to'");
    }

    const headers = {
      'Authorization': `Bearer ${config.access_token}`,
      'Content-Type': 'application/json'
    };

    const payload = {
      to: config.to, // LINE user ID or group ID
      messages: [
        {
          type: 'text',
          text: message
        }
      ]
    };

    const response = await axios.post('https://api.line.me/v2/bot/message/push', payload, { headers });
    return response.data;
  }
};
