<?php
declare(strict_types=1);

/**
 * Full-screen discussion modal (admin ↔ manager).
 *
 * @var int $qt_chat_quote_id
 * @var string $qt_chat_quote_label
 * @var string $qt_chat_customer_name
 * @var string $qt_chat_viewer_role
 * @var list<array<string,mixed>> $qt_chat_messages
 */
$qt_chat_quote_id = (int) ($qt_chat_quote_id ?? 0);
$qt_chat_quote_label = trim((string) ($qt_chat_quote_label ?? ''));
$qt_chat_customer_name = trim((string) ($qt_chat_customer_name ?? ''));
$qt_chat_viewer_role = strtolower((string) ($qt_chat_viewer_role ?? 'manager'));
$qt_chat_messages = $qt_chat_messages ?? [];
$qt_chat_peer_label = $qt_chat_viewer_role === 'admin' ? 'Manager' : 'Admin';
?>
<div id="qtChatModal" class="qt-chat-modal" hidden role="dialog" aria-modal="true" aria-labelledby="qtChatModalTitle">
  <div class="qt-chat-modal-backdrop" data-qt-chat-close aria-hidden="true"></div>
  <div class="qt-chat-modal-dialog">
    <header class="qt-chat-modal-header">
      <div class="qt-chat-modal-header-text">
        <p class="qt-chat-modal-eyebrow"><i class="fas fa-comments" aria-hidden="true"></i> Quotation discussion</p>
        <h2 id="qtChatModalTitle" class="qt-chat-modal-title">
          <?php echo htmlspecialchars($qt_chat_quote_label !== '' ? $qt_chat_quote_label : 'Quotation', ENT_QUOTES, 'UTF-8'); ?>
          <?php if ($qt_chat_customer_name !== ''): ?>
          <span class="qt-chat-modal-subtitle"><?php echo htmlspecialchars($qt_chat_customer_name, ENT_QUOTES, 'UTF-8'); ?></span>
          <?php endif; ?>
        </h2>
        <p class="qt-chat-modal-desc">Private thread with <?php echo htmlspecialchars($qt_chat_peer_label, ENT_QUOTES, 'UTF-8'); ?> — not printed on the PDF.</p>
      </div>
      <button type="button" class="qt-chat-modal-close" data-qt-chat-close aria-label="Close discussion">
        <i class="fas fa-times" aria-hidden="true"></i>
      </button>
    </header>
    <div id="qt-chat-modal-messages" class="qt-chat-modal-messages" role="log" aria-live="polite"></div>
    <footer class="qt-chat-modal-footer">
      <label class="qt-chat-modal-label" for="qt-chat-modal-input">Your message</label>
      <div class="qt-chat-modal-compose">
        <textarea id="qt-chat-modal-input" class="qt-chat-modal-input" rows="3" maxlength="2000" placeholder="Type your message…"></textarea>
        <button type="button" class="erp-btn erp-btn-primary qt-chat-modal-send" id="qt-chat-modal-send">
          <i class="fas fa-paper-plane" aria-hidden="true"></i> Send
        </button>
      </div>
      <p id="qt-chat-modal-error" class="qt-chat-modal-error" hidden role="alert"></p>
    </footer>
  </div>
</div>
