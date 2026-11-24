FROM node:26-alpine@sha256:6bd9ab43c0ec0190ca7b2d193906cb8b7edf6e7f4afa43ddeccd6452710bffb5

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