#!/usr/bin/env bash
set -Eeuo pipefail

target_ip="$(getent ahostsv4 smtp.mail.yahoo.com | awk 'NR == 1 {print $1}')"
capture="/tmp/yahoo-smtp-tcpdump.txt"
tls_output="/tmp/yahoo-smtp-openssl.txt"

timeout 20 tcpdump -nn -tttt -i any "host ${target_ip} and tcp port 587" -c 80 >"${capture}" 2>&1 &
capture_pid=$!
sleep 1
timeout 15 openssl s_client -4 -starttls smtp \
  -connect "${target_ip}:587" \
  -servername smtp.mail.yahoo.com \
  -state -brief </dev/null >"${tls_output}" 2>&1 || true
wait "${capture_pid}" || true

echo "TARGET_IP=${target_ip}"
echo '=== OPENSSL ==='
cat "${tls_output}"
echo '=== TCP SUMMARY ==='
cat "${capture}"
