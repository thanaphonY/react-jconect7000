const axios = require('axios');

module.exports = {
  async send(config, message) {
    try {
      const { bot_token, chat_id } = config;
      
      if (!bot_token) {
        throw new Error('Telegram bot token is required');
      }
      
      if (!chat_id) {
        throw new Error('Telegram chat ID is required');
      }
      
      // Telegram Bot API
      const url = `https://api.telegram.org/bot${bot_token}/sendMessage`;
      const response = await axios.post(url, {
        chat_id: chat_id,
        text: message,
      });
      
      if (response.data.ok) {
        console.log(`[TELEGRAM] Message sent successfully to chat ${chat_id}`);
        return { success: true, message: 'Telegram message sent' };
      } else {
        throw new Error(`Telegram API error: ${response.data.description}`);
      }
      
    } catch (error) {
      console.error('[TELEGRAM ERROR]:', error.message);
      throw error;
    }
  }
}; 