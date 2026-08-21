<?php
declare(strict_types=1);

/**
 * Quotation admin ↔ manager discussion (sidebar / CRM panel).
 *
 * @var int $qt_chat_quote_id
 * @var list<array<string,mixed>> $qt_chat_messages
 * @var string $qt_chat_viewer_role admin|manager
 * @var string $qt_chat_api_url
 */
$qt_chat_quote_id = (int) ($qt_chat_quote_id ?? 0);
$qt_chat_messages = $qt_chat_messages ?? [];
$qt_chat_viewer_role = strtolower((string) ($qt_chat_viewer_role ?? 'manager'));
$qt_chat_api_url = (string) ($qt_chat_api_url ?? '');
$qt_chat_compact = !empty($qt_chat_compact);
?>
<div class="qt-chat-panel<?php echo $qt_chat_compact ? ' qt-chat-panel--compact' : ''; ?>" id="qt-chat-panel" data-qt-chat-role="<?php echo htmlspecialchars($qt_chat_viewer_role, ENT_QUOTES, 'UTF-8'); ?>">
  <div class="qt-chat-head">
    <h3><i class="fas fa-comments" aria-hidden="true"></i> Discussion</h3>
    <span class="qt-chat-hint">Admin &amp; manager only · not on the PDF</span>
  </div>
  <div id="qt-chat-messages" class="qt-chat-messages" role="log" aria-live="polite">
    <?php if ($qt_chat_messages === []): ?>
    <p class="qt-chat-empty" id="qt-chat-empty">No messages yet. Start the conversation below.</p>
    <?php else: ?>
    <?php foreach ($qt_chat_messages as $msg): ?>
    <?php
    $msgRole = strtolower((string) ($msg['by_role'] ?? 'manager'));
    $isMine = $msgRole === $qt_chat_viewer_role;
    ?>
    <article class="qt-chat-bubble<?php echo $isMine ? ' qt-chat-bubble--mine' : ' qt-chat-bubble--theirs'; ?><?php echo $msgRole === 'admin' ? ' qt-chat-bubble--admin' : ' qt-chat-bubble--manager'; ?>" data-msg-role="<?php echo htmlspecialchars($msgRole, ENT_QUOTES, 'UTF-8'); ?>">
      <p class="qt-chat-bubble-meta">
        <strong><?php echo htmlspecialchars((string) ($msg['by_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
        <span><?php echo htmlspecialchars($msgRole === 'admin' ? 'Admin' : 'Manager', ENT_QUOTES, 'UTF-8'); ?></span>
        <?php if (!empty($msg['at'])): ?>
        <span> · <?php echo htmlspecialchars(date('d M g:i A', strtotime((string) $msg['at'])), ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
      </p>
      <p class="qt-chat-bubble-text"><?php echo nl2br(htmlspecialchars((string) ($msg['text'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
    </article>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div class="qt-chat-compose">
    <label class="qt-chat-label" for="qt-chat-input">Your message</label>
    <textarea id="qt-chat-input" class="qt-chat-input" rows="3" maxlength="2000" placeholder="Type a message…"></textarea>
    <p id="qt-chat-error" class="qt-chat-error" hidden role="alert"></p>
    <button type="button" class="erp-btn erp-btn-primary qt-chat-send" id="qt-chat-send"><i class="fas fa-paper-plane"></i> Send</button>
  </div>
</div>
