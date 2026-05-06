# syntax=docker/dockerfile:1.7
#
# Multi-stage production Dockerfile for the Next.js frontend.
#
# IMPORTANT: This image relies on Next.js standalone output. Ensure that
# `frontend/next.config.js` contains:
#
#     output: 'standalone'
#
# Without that flag the runtime stage below will not find `.next/standalone`
# and the image build will fail. The next.config.js file is treated as
# source and is NOT modified by this infrastructure change.

############################
# Stage 1: deps — install production-capable node_modules with a clean lockfile
############################
FROM node:20-alpine AS deps
WORKDIR /app

# libc6-compat is occasionally required by Next.js native deps on alpine
RUN apk add --no-cache libc6-compat

COPY package.json package-lock.json* ./
RUN npm ci

############################
# Stage 2: builder — compile the Next.js app
############################
FROM node:20-alpine AS builder
WORKDIR /app

ENV NEXT_TELEMETRY_DISABLED=1

COPY --from=deps /app/node_modules ./node_modules
COPY . .

RUN npm run build

############################
# Stage 3: runner — minimal runtime image
############################
FROM node:20-alpine AS runner
WORKDIR /app

ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# The `node` user (uid 1000) ships with the official node:alpine images.
# Create a writeable .next directory owned by it before dropping privileges.
RUN mkdir -p /app/.next && chown -R node:node /app

# Copy the standalone server, static assets, and public/ from the builder.
# `output: 'standalone'` in next.config.js is required for these paths to exist.
COPY --from=builder --chown=node:node /app/public ./public
COPY --from=builder --chown=node:node /app/.next/standalone ./
COPY --from=builder --chown=node:node /app/.next/static ./.next/static

USER node

EXPOSE 3000

CMD ["node", "server.js"]
