FROM node:24-alpine@sha256:f70403e87646dc51b45295f4b8b70cdad0b63d2297c4c9899119b03f7af7a6b3

# Install Chromium and dependencies for Puppeteer
RUN apk add --no-cache \
    chromium \
    nss \
    freetype \
    harfbuzz \
    ca-certificates \
    ttf-freefont

# Tell Puppeteer to use installed Chromium
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium-browser

# Install Pa11y globally
RUN npm install -g pa11y

# Set working directory
WORKDIR /app

# Copy config file with no-sandbox setting for containerized Chromium
COPY pa11y.json /app/pa11y.json

ENTRYPOINT ["pa11y", "--config", "/app/pa11y.json"]