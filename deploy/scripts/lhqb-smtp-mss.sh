#!/usr/bin/env bash
set -Eeuo pipefail

ACTION="${1:-apply}"
RULE=(-p tcp -m multiport --dports 465,587 --tcp-flags SYN,RST SYN -j TCPMSS --set-mss 1300)

remove_rule() {
  while iptables -t mangle -C OUTPUT "${RULE[@]}" 2>/dev/null; do
    iptables -t mangle -D OUTPUT "${RULE[@]}"
  done
}

case "${ACTION}" in
  apply)
    remove_rule
    iptables -t mangle -I OUTPUT 1 "${RULE[@]}"
    ;;
  remove)
    remove_rule
    ;;
  *)
    echo "Usage: $0 {apply|remove}" >&2
    exit 2
    ;;
esac
