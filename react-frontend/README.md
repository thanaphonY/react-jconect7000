# React + Vite + TypeScript connection foundation

Run the Go API on 127.0.0.1:3000, then `npm install` and `npm run dev` here.
Open http://127.0.0.1:5173. `/api` is proxied to Go; database credentials are never sent to React.

This is a live connection verification page, not a replacement for the existing PHP UI. Full PHP page functionality is still pending. Production hosting/proxy is deliberately not changed; the Vite proxy applies only during development.
