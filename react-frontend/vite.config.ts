import { defineConfig } from 'vite';

export default defineConfig({
 build:{rollupOptions:{onwarn(warning,warn){if(warning.code==='MODULE_LEVEL_DIRECTIVE'&&warning.message.includes('use client'))return;warn(warning)},output:{manualChunks(id){if(id.includes('/node_modules/echarts/')||id.includes('/node_modules/zrender/'))return 'charts';if(id.includes('/node_modules/gridstack/'))return 'gridstack'}}}},
 server: {
  host: '127.0.0.1',
  port: 5173,
  strictPort: true,
  proxy: { '/api': { target: 'http://127.0.0.1:3000', changeOrigin: true }, '/assets': {target:'http://127.0.0.1:3000'}, '/media': {target:'http://127.0.0.1:3000'}, '/ws':{target:'ws://127.0.0.1:3000',ws:true} },
 },
});
