<div class="modal-overlay" id="orderModal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
<div class="modal">
<button class="modal-close" id="modalClose" aria-label="Закрыть">×</button>
<div class="modal-title" id="orderModalTitle">Заявка</div>
<div class="modal-sub" id="modalSub"></div>
<div class="detail-grid">
<div class="detail-image"><img id="modalImage" src="" alt="Фото товара"></div>
<div class="detail-rows">
<div class="detail-row"><div class="k">Клиент</div><div class="v" id="dName">—</div></div>
<div class="detail-row"><div class="k">Телефон</div><div class="v" id="dPhone">—</div></div>
<div class="detail-row"><div class="k">Email</div><div class="v" id="dEmail">—</div></div>
<div class="detail-row"><div class="k">Товар</div><div class="v" id="dProduct">—</div></div>
<div class="detail-row"><div class="k">Цена</div><div class="v" id="dPrice">—</div></div>
<div class="detail-row"><div class="k">Создана</div><div class="v" id="dDate">—</div></div>
<div class="detail-row"><div class="k">IP</div><div class="v mono" id="dIp">—</div></div>
<div class="detail-row"><div class="k">User-Agent</div><div class="v mono" id="dUa">—</div></div>
<div class="detail-row"><div class="k">Статус</div><div class="v" id="dStatus">—</div></div>
</div>
</div>
<div class="modal-actions">
<?php if (users_can_do($currentUser, 'orders.accept')): ?>
<button type="button" class="btn btn-accept" id="modalAccept">Принять</button>
<?php endif; ?>
<?php if (users_can_do($currentUser, 'orders.reject')): ?>
<button type="button" class="btn btn-reject" id="modalReject">Отклонить</button>
<?php endif; ?>
</div>
</div>
</div>