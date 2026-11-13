FROM node:22-alpine
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev
COPY server.js ./
ENV TZ=Asia/Bangkok
EXPOSE 8080
CMD ["node", "server.js"]
