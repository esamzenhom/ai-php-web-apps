FROM node:22-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends python3 ca-certificates git && rm -rf /var/lib/apt/lists/* \
    && npm install -g @openai/codex@0.153.4 @anthropic-ai/claude-code@2.1.263 \
    && npm cache clean --force
WORKDIR /opt/runner
COPY docker/cli/server.py /opt/runner/server.py
ENV HOME=/state TMPDIR=/state/tmp CODEX_HOME=/state/codex CLAUDE_CONFIG_DIR=/state/claude
CMD ["python3", "/opt/runner/server.py"]
