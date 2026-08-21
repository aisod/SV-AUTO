<?php
declare(strict_types=1);

/**
 * Sidebar teaser — opens the discussion modal.
 *
 * @var list<array<string,mixed>> $qt_chat_messages
 * @var string $qt_chat_viewer_role
 */
$qt_chat_messages = $qt_chat_messages ?? [];
$qt_chat_viewer_role = strtolower((string) ($qt_chat_viewer_role ?? 'manager'));
$msgCount = count($qt_chat_messages);
$lastPreview = '';
if ($msgCount > 0) {
    $last = $qt_chat_messages[$msgCount - 1];
    $lastPreview = trim((string) ($last['text'] ?? ''));
    if (function_exists('mb_strlen') && mb_strlen($lastPreview) > 72) {
        $lastPreview = mb_substr($lastPreview, 0, 69) . '…';
    } elseif (strlen($lastPreview) > 72) {
        $lastPreview = substr($lastPreview, 0, 69) . '…';
    }
}
$peer = $qt_chat_viewer_role === 'admin' ? 'manager' : 'admin';
?>
<div class="qt-chat-teaser" id="qt-chat-teaser">
  <div class="qt-chat-teaser-icon" aria-hidden="true"><i class="fas fa-comments"></i></div>
  <div class="qt-chat-teaser-body">
    <p class="qt-chat-teaser-title">Discussion with <?php echo htmlspecialchars($peer, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($msgCount > 0): ?>
    <p class="qt-chat-teaser-meta"><?php echo (int) $msgCount; ?> message<?php echo $msgCount === 1 ? '' : 's'; ?><?php if ($lastPreview !== ''): ?> · “<?php echo htmlspecialchars($lastPreview, ENT_QUOTES, 'UTF-8'); ?>”<?php endif; ?></p>
    <?php else: ?>
    <p class="qt-chat-teaser-meta">No messages yet — start the conversation.</p>
    <?php endif; ?>
  </div>
  <button type="button" class="erp-btn erp-btn-primary qt-chat-teaser-btn" id="qt-chat-open-btn" data-qt-open-chat>
    <i class="fas fa-comment-dots" aria-hidden="true"></i> Open chat
  </button>
</div>
