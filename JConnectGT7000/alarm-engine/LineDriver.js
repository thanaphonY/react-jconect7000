const axios = require('axios');

module.exports = {
  async send(config, message) {
    const token = config.access_token || '';
    const res = await axios.post(
      'https://notify-api.line.me/api/notify',
      new URLSearchParams({ message }),
      {
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          Authorization: `Bearer ${token}`
        }
      }
    );
    return res.data;
  }
};