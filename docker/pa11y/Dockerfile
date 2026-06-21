FROM node:24-alpine@sha256:156b55f92e98ccd5ef49578a8cea0df4679826564bad1c9d4ef04462b9f0ded6

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