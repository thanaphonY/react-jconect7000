// Shared realtime buffer for metrics/events.
// This mirrors Gen2 behavior and is intentionally simple (in-memory).
let buffer = [];
const MAX_BUFFER_SIZE = 5000;

function queueMetric(metric) {
  buffer.push(metric);
  if (buffer.length > MAX_BUFFER_SIZE) {
    buffer = buffer.slice(-MAX_BUFFER_SIZE);
  }
}

function getBufferChunk(size) {
  return buffer.splice(0, size);
}

function hasBuffer() {
  return buffer.length > 0;
}

function bufferLength() {
  return buffer.length;
}

function clearBuffer() {
  buffer = [];
}

module.exports = {
  queueMetric,
  getBufferChunk,
  hasBuffer,
  bufferLength,
  clearBuffer,
};

