FROM semgrep/semgrep:1.176.0
COPY docker/audit/server.py /opt/audit/server.py
COPY docker/audit/rules.yml /opt/audit/rules.yml
ENV HOME=/tmp SEMGREP_SEND_METRICS=off SEMGREP_ENABLE_VERSION_CHECK=0
ENTRYPOINT ["python3", "/opt/audit/server.py"]
