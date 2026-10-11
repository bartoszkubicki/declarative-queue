#!/usr/bin/env bash
# Checks queue and binding arguments created by the example module on a running RabbitMQ (e.g. a Warden environment).
# Run from the Magento root: bash app/code/BKubicki/QueueDeclarationExample/e2e.sh
# Environment: RABBITMQ_API (default http://rabbitmq:15672/api), RABBITMQ_AUTH (default guest:guest)
set -u
API=${RABBITMQ_API:-http://rabbitmq:15672/api}
AUTH=${RABBITMQ_AUTH:-guest:guest}
FAILED=0

api() { curl -s -u "$AUTH" -X "$1" "$API$2"; }

# json <document> <php expression on $d>, prints the expression result
json() { php -r '$d = json_decode($argv[1], true); echo json_encode(eval("return " . $argv[2] . ";"));' "$1" "$2"; }

check() { # description, expected, actual
    if [ "$2" == "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1 (expected $2, got $3)"; FAILED=1; fi
}

echo "== delete broker objects of previous runs (queue arguments cannot be changed on an existing queue)"
for q in declared undeclared declared_unbound; do api DELETE "/queues/%2F/example.$q" > /dev/null; done
api DELETE "/exchanges/%2F/example.exchange" > /dev/null

echo "== install topology (setup:upgrade)"
php bin/magento module:enable BKubicki_QueueDeclarationExample > /dev/null
php -d memory_limit=-1 bin/magento setup:upgrade --keep-generated > /dev/null 2>&1 || { echo "FAIL  setup:upgrade"; exit 1; }

echo "== queues"
q=$(api GET /queues/%2F/example.declared)
check "declared queue has its dead letter exchange" \
    '"example.dead_letter"' "$(json "$q" '$d["arguments"]["x-dead-letter-exchange"] ?? null')"
check "declared queue has its ttl" '5000' "$(json "$q" '$d["arguments"]["x-message-ttl"] ?? null')"
check "declared queue did not get the binding argument" \
    'null' "$(json "$q" '$d["arguments"]["binding-only-argument"] ?? null')"
q=$(api GET /queues/%2F/example.undeclared)
check "queue without declaration did not get ttl from its binding" \
    'null' "$(json "$q" '$d["arguments"]["x-message-ttl"] ?? null')"
q=$(api GET /queues/%2F/example.declared_unbound)
check "declared queue without binding exists with its own settings" \
    '[false,true]' "$(json "$q" '[$d["durable"], $d["auto_delete"]]')"

echo "== bindings"
b=$(api GET /bindings/%2F/e/example.exchange/q/example.declared)
check "binding to declared queue keeps its own argument" \
    '"binding-value"' "$(json "$b" '$d[0]["arguments"]["binding-only-argument"] ?? null')"
check "binding to declared queue did not get queue arguments" \
    'null' "$(json "$b" '$d[0]["arguments"]["x-dead-letter-exchange"] ?? null')"
b=$(api GET /bindings/%2F/e/example.exchange/q/example.undeclared)
check "binding to queue without declaration keeps its argument" \
    '1000' "$(json "$b" '$d[0]["arguments"]["x-message-ttl"] ?? null')"

[ $FAILED -eq 0 ] && echo "ALL PASSED" || { echo "SOME CHECKS FAILED"; exit 1; }
