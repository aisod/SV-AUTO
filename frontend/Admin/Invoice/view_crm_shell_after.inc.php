            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<script>
(function(){
  var INVOICE_ID=<?php echo (int)$invoiceId; ?>;
  var INVOICE_REF=<?php echo json_encode($invNumber ?: ('invoice_'.$invoiceId)); ?>;
  function node(){ return document.querySelector('#inv-live-doc-mount #aq-print-inner'); }
  function docCss(){ var el=document.getElementById('inv-doc-print-styles'); return el?el.textContent:''; }
  function html(n){ var t=String(INVOICE_REF).replace(/[^\w\-]+/g,'_'); var body='<div id="inv-live-doc-mount">'+n.outerHTML+'</div>'; return '<!DOCTYPE html><html><head><meta charset=UTF-8"><title>'+t+'</title><style>'+docCss()+'@page{size:A4 portrait;margin:10mm}body{margin:0;background:#fff;font-family:Arial,Helvetica,sans-serif;font-size:9pt}</style></head><body>'+body+'</body></html>'; }
  function invPageUrl(extraQs){
    var u=location.pathname;
    var q='?id='+encodeURIComponent(String(INVOICE_ID));
    if(extraQs){ q+=(q.indexOf('?')>=0?'&':'?')+extraQs; }
    return u+q;
  }
  function bindDownload(btn){
    if(!btn) return;
    btn.onclick=function(){
      var n=node(); if(!n)return;
      var fd=new FormData();
      fd.append('_inv_pdf','1');
      fd.append('doc_html',html(n.cloneNode(true)));
      fd.append('filename',INVOICE_REF);
      fetch(invPageUrl(), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.ok ? r.blob() : r.text().then(function(t){ throw new Error(t || 'PDF failed'); }); })
        .then(function(b){ var u=URL.createObjectURL(b),a=document.createElement('a'); a.href=u; a.download=INVOICE_REF+'.pdf'; a.click(); })
        .catch(function(e){ alert(e.message||'PDF failed'); });
    };
  }
  document.querySelectorAll('[data-inv-download]').forEach(bindDownload);
  function doPrint(){ var n=node(); if(!n)return; var f=document.createElement('iframe'); f.style.cssText='position:fixed;width:0;height:0;border:0'; document.body.appendChild(f); f.contentWindow.document.write(html(n.cloneNode(true))); f.contentWindow.document.close(); setTimeout(function(){f.contentWindow.print();f.remove();},400); }
  document.querySelectorAll('[data-inv-print]').forEach(function(btn){ btn.onclick=doPrint; });

  var actionsBtn=document.getElementById('crm-cust-actions-btn');
  var actionsMenu=document.getElementById('crm-cust-actions-menu');
  var actionsWrap=actionsBtn?actionsBtn.closest('.crm-cust-actions-wrap'):null;
  function isActionsOpen(){ return actionsMenu && !actionsMenu.hidden; }
  function openActions(){
    if(!actionsMenu||!actionsBtn) return;
    actionsMenu.hidden=false;
    actionsMenu.removeAttribute('hidden');
    actionsBtn.setAttribute('aria-expanded','true');
    if(actionsWrap){ actionsWrap.classList.add('is-open'); }
  }
  function closeActions(){
    if(actionsMenu){
      actionsMenu.hidden=true;
      actionsMenu.setAttribute('hidden','');
    }
    if(actionsBtn){ actionsBtn.setAttribute('aria-expanded','false'); }
    if(actionsWrap){ actionsWrap.classList.remove('is-open'); }
  }
  function toggleActions(e){
    if(e){ e.preventDefault(); e.stopPropagation(); }
    if(isActionsOpen()){ closeActions(); } else { openActions(); }
  }
  if(actionsBtn&&actionsMenu){
    actionsBtn.addEventListener('click', toggleActions);
  }
  var cardMenuBtn=document.getElementById('crm-cust-card-menu');
  var cardPopover=document.getElementById('crm-cust-card-popover');
  var cardMenuWrap=cardMenuBtn?cardMenuBtn.closest('.crm-cust-hd-menu'):null;
  function closeCardPopover(){
    if(cardPopover){
      cardPopover.hidden=true;
      cardPopover.setAttribute('hidden','');
    }
    if(cardMenuBtn){ cardMenuBtn.setAttribute('aria-expanded','false'); }
  }
  function toggleCardPopover(e){
    if(e){ e.preventDefault(); e.stopPropagation(); }
    if(!cardPopover||!cardMenuBtn) return;
    var willOpen=cardPopover.hidden;
    closeCardPopover();
    closeActions();
    if(willOpen){
      cardPopover.hidden=false;
      cardPopover.removeAttribute('hidden');
      cardMenuBtn.setAttribute('aria-expanded','true');
    }
  }
  if(cardMenuBtn&&cardPopover){
    cardMenuBtn.addEventListener('click', toggleCardPopover);
  }
  document.addEventListener('click',function(e){
    if(actionsWrap&&actionsWrap.contains(e.target)) return;
    if(cardMenuWrap&&cardMenuWrap.contains(e.target)) return;
    closeActions();
    closeCardPopover();
  });

  function stripFlashQuery(){
    var u=new URL(window.location.href);
    var changed=false;
    if(u.searchParams.has('success')){ u.searchParams.delete('success'); changed=true; }
    if(u.searchParams.has('error')){ u.searchParams.delete('error'); changed=true; }
    if(changed){ window.history.replaceState({},'',u.toString()); }
  }

  var printBlankBtn=document.getElementById('crm-cust-print-blank');
  if(printBlankBtn){
    printBlankBtn.onclick=function(){
      closeActions();
      window.location.href=invPageUrl('print_blank=1');
    };
  }
  var qs=new URLSearchParams(window.location.search);
  if(qs.get('print_blank')==='1'){
    setTimeout(function(){
      doPrint();
      var u=new URL(window.location.href);
      u.searchParams.delete('print_blank');
      window.history.replaceState({},'',u.toString());
    }, 700);
  }

  window.addEventListener('load', function () {
    var wrap = document.getElementById('invViewFlashWrap');
    if (!wrap) return;
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    setTimeout(function () {
      wrap.classList.remove('show');
      setTimeout(function () {
        wrap.remove();
        stripFlashQuery();
      }, 220);
    }, 3200);
  });

  function showInvMgrSendModal(opts) {
    var wrap = document.getElementById('invMgrSendModal');
    var card = wrap ? wrap.querySelector('.inv-flash-card') : null;
    var titleEl = document.getElementById('invMgrSendModalTitle');
    var bodyEl = document.getElementById('invMgrSendModalBody');
    var hintEl = document.getElementById('invMgrSendModalHint');
    var iconEl = document.getElementById('invMgrSendModalIcon');
    if (!wrap || !card || !titleEl || !bodyEl) {
      alert((opts && opts.body) || 'Done');
      return;
    }
    var ok = !opts || opts.ok !== false;
    titleEl.textContent = (opts && opts.title) || (ok ? 'Sent for manager preview' : 'Send failed');
    bodyEl.textContent = (opts && opts.body) || '';
    if (hintEl) {
      hintEl.style.display = ok ? '' : 'none';
    }
    card.classList.remove('inv-flash-card--success', 'inv-flash-card--error');
    card.classList.add(ok ? 'inv-flash-card--success' : 'inv-flash-card--error');
    if (iconEl) {
      iconEl.innerHTML = ok
        ? '<i class="fas fa-paper-plane"></i>'
        : '<i class="fas fa-exclamation-circle"></i>';
    }
    wrap.hidden = false;
    wrap.removeAttribute('hidden');
    wrap.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    function closeModal() {
      wrap.classList.remove('show');
      wrap.setAttribute('aria-hidden', 'true');
      setTimeout(function () {
        wrap.hidden = true;
        wrap.setAttribute('hidden', '');
      }, 200);
    }
    var okBtn = document.getElementById('invMgrSendModalOk');
    var closeBtn = document.getElementById('invMgrSendModalClose');
    if (okBtn) { okBtn.onclick = closeModal; }
    if (closeBtn) { closeBtn.onclick = closeModal; }
    wrap.onclick = function (e) { if (e.target === wrap) closeModal(); };
    document.addEventListener('keydown', function onKey(e) {
      if (e.key === 'Escape') closeModal();
    }, { once: true });
  }

  function updateInvMgrReviewUi(j){
    var meta=document.getElementById('inv-mgr-review-meta');
    var btn=document.getElementById('inv-send-manager-review');
    var sentAt=j&&j.sent_at?String(j.sent_at):'';
    var sentBy=j&&j.sent_by?String(j.sent_by):'';
    if(meta){
      if(sentAt!==''){
        meta.style.display='';
        var parts=['Last sent '+sentAt];
        if(sentBy!=='') parts.push('by '+sentBy);
        meta.textContent=parts.join(' ');
      }else{
        meta.style.display='none';
        meta.textContent='';
      }
    }
    if(btn){
      btn.className='crm-cust-action-btn crm-cust-action-btn--preview';
      btn.innerHTML=sentAt!==''?'<i class="fas fa-paper-plane"></i> Send again to manager':'<i class="fas fa-paper-plane"></i> Send to manager for review';
    }
  }
  var invSendMgrBtn = document.getElementById('inv-send-manager-review');
  if (invSendMgrBtn) {
    invSendMgrBtn.onclick = function () {
      invSendMgrBtn.disabled = true;
      var fd = new FormData();
      fd.append('_action', 'send_to_manager_review');
      fd.append('invoice_id', String(INVOICE_ID));
      fetch(invPageUrl(), { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(async function (r) {
          var raw = await r.text();
          var j = null;
          try { j = JSON.parse(raw); } catch (_) { j = null; }
          if (!j || !j.ok) {
            var err = (j && j.error) ? String(j.error) : 'Send failed';
            if (raw && /^\s*</.test(raw)) {
              err = 'Server returned a page instead of JSON. Refresh the invoice page and try again.';
            }
            throw new Error(err);
          }
          return j;
        })
        .then(function (j) {
          updateInvMgrReviewUi(j);
          var body = j.notified > 0
            ? 'The invoice was sent for manager review. ' + j.notified + ' notification(s) were delivered to manager accounts.'
            : 'The invoice was sent for manager review. No manager user accounts were found to notify — managers can still open it from their invoice list once they have access.';
          showInvMgrSendModal({
            ok: true,
            title: 'Sent for manager preview',
            body: body
          });
        })
        .catch(function (e) {
          showInvMgrSendModal({
            ok: false,
            title: 'Could not send',
            body: e.message || 'Send failed'
          });
        })
        .finally(function () { invSendMgrBtn.disabled = false; });
    };
  }
})();
</script>
