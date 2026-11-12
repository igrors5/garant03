<?php /* Модалка заявки нужна только в orders view, но оставим всегда — она скрыта */ ?>
</main>
</div>

<?php require __DIR__ . '/modal_order.php'; ?>

<script>
window.ORDERS = <?= json_encode(array_map(function($o) {
    return [
        'id'         => (string)($o['id'] ?? ''),
        'last_name'  => (string)($o['last_name'] ?? ''),
        'first_name' => (string)($o['first_name'] ?? ''),
        'phone'      => (string)($o['phone'] ?? ''),
        'email'      => (string)($o['email'] ?? ''),
        'product'    => (string)($o['product'] ?? ''),
        'price'      => (int)($o['price'] ?? 0),
        'created_at' => (string)($o['created_at'] ?? ''),
        'ip'         => (string)($o['ip'] ?? ''),
        'user_agent' => (string)($o['user_agent'] ?? ''),
        'status'     => (string)($o['status'] ?? 'new'),
        'image'      => productImage((string)($o['product'] ?? '')),
    ];
}, $filteredOrders ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

window.ROLE_PRESETS = <?= json_encode(ROLE_PRESETS, JSON_UNESCAPED_UNICODE) ?>;
window.ROLE_ACTION_PRESETS = <?= json_encode(ROLE_ACTION_PRESETS, JSON_UNESCAPED_UNICODE) ?>;
window.CURRENT_VIEW = <?= json_encode($view) ?>;
</script>
<script src="assets/js/admin.js"></script>
</body>
</html>