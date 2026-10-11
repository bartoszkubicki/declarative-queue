### Unreleased ###
* initial release, extracted from `bkubicki/message-queue` (commit `0a7ad2f612d65543d0c9e91b5a98acd85c1ab93d`):
  * queues no longer get arguments from bindings. Core takes queue arguments from `<binding><arguments>` since
  magento/magento2#26966, so they end up on both the queue and the binding; now queues derived from bindings
  (including RPC response queues) are created without arguments and binding arguments are passed to the binding only
  * added `queue_declaration.xml` (schema `urn:magento:module:BKubicki_DeclarativeQueue:etc/queue_declaration.xsd`)
  which declares queues explicitly with their own `durable`, `autoDelete` and `<arguments>`; files of all modules are
  merged. Declared queues take precedence over, and are created in addition to, the ones derived from bindings
* renamed the plugin's internal cache id from `message_queue_queue_declaration_config_cache` to
`declarative_queue_declaration_config_cache` to match the new standalone package name
