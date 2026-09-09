<?php
function render_layout($title, $active, $content) {
    $user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-inner">
        <a href="/" class="nav-brand"><?= SITE_NAME ?></a>
        <form action="/search" method="get" class="nav-search">
            <input type="text" name="q" placeholder="Search characters, corporations, systems..." class="nav-search-input" autocomplete="off">
            <button type="submit" class="nav-search-btn">Search</button>
        </form>
        <div class="nav-links">
            <a href="/kills" class="<?= $active==='kills'?'active':'' ?>">Killboard</a>
            <div class="nav-drop <?= in_array($active,['players','systems','sov'],true)?'active':'' ?>">
                <a>World</a>
                <div class="nav-drop-menu">
                    <a href="/players" class="<?= $active==='players'?'active':'' ?>">Players</a>
                    <a href="/systems" class="<?= $active==='systems'?'active':'' ?>">Systems</a>
                    <a href="/sov" class="<?= $active==='sov'?'active':'' ?>">Sovereignty</a>
                    <a href="/battles">Battles</a>
                </div>
            </div>
            <div class="nav-drop <?= in_array($active,['market','haul'],true)?'active':'' ?>">
                <a>Trade</a>
                <div class="nav-drop-menu">
                    <a href="/market" class="<?= $active==='market'?'active':'' ?>">Market</a>
                    <a href="/haul" class="<?= $active==='haul'?'active':'' ?>">Haul</a>
                </div>
            </div>
            <?php if ($user): ?>
                <div class="nav-drop right <?= in_array($active,['chars','petitions','mail','admin'],true)?'active':'' ?>">
                    <a><?= e($user['accountName']) ?></a>
                    <div class="nav-drop-menu">
                        <a href="/characters" class="<?= $active==='chars'?'active':'' ?>">My Characters</a>
                        <a href="/petitions" class="<?= $active==='petitions'?'active':'' ?>">Petitions</a>
                        <a href="/mail" class="<?= $active==='mail'?'active':'' ?>">Mail <span id="mailBadge" style="display:none;background:var(--accent2);color:#06121f;border-radius:9px;padding:0 6px;margin-left:4px;font-size:11px;font-weight:700">0</span></a>
                        <?php if ($user['role'] & (ROLE_ADMIN|ROLE_GMH|ROLE_GML)): ?>
                            <a href="/admin" class="<?= $active==='admin'?'active':'' ?>">Admin</a>
                        <?php endif; ?>
                        <a href="/logout">Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login" class="nav-btn">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container">
<?= $content ?>
</main>
<footer class="footer">
    <?= SITE_NAME ?> Killboard &copy; <?= date('Y') ?> &mdash; Portal v<?= PORTAL_VERSION ?> &mdash; Powered by EVEmu &mdash; <a href="/rules" style="color:var(--text-dim)">Rules</a>
</footer>

<style>
    #themeBtn{position:fixed;right:14px;bottom:14px;z-index:500;width:40px;height:40px;border-radius:50%;border:1px solid var(--border);background:var(--bg-card);color:var(--text);cursor:pointer;font-size:18px;box-shadow:0 2px 8px rgba(0,0,0,.4)}
    #themeMenu{position:fixed;right:14px;bottom:62px;z-index:501;background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:8px;display:none;min-width:170px;box-shadow:0 4px 14px rgba(0,0,0,.45)}
    #themeMenu a{display:block;padding:6px 10px;font-size:13px;color:var(--text);text-decoration:none;border-radius:6px;cursor:pointer}
    #themeMenu a:hover{background:var(--bg-hover)}
    #themeMenu a.on{color:var(--accent2);font-weight:600}
</style>
<button id="themeBtn" title="Тема оформления">🎨</button>
<div id="themeMenu"></div>
<script>
(function(){
  var PRESETS = {
    classic: {name:'Классика', vars:{'--bg':'#0a0e14','--bg-card':'#111820','--bg-hover':'#151d28','--bg-input':'#0d1117','--border':'#1a2030','--text':'#c5c8c6','--text-dim':'#667788','--text-bright':'#e0e0e0','--accent':'#66cc88','--accent2':'#4a9eff'}},
    blue:    {name:'Синяя ночь', vars:{'--bg':'#0a1020','--bg-card':'#101a2e','--bg-hover':'#16233b','--bg-input':'#0b1120','--border':'#22334f','--text':'#c9d6ea','--text-dim':'#6a7f9e','--text-bright':'#e4ecf7','--accent':'#4a9eff','--accent2':'#66cc88'}},
    amethyst:{name:'Аметист', vars:{'--bg':'#160f20','--bg-card':'#201431','--bg-hover':'#2a1b40','--bg-input':'#130c1c','--border':'#38284f','--text':'#d8ccec','--text-dim':'#8c7aa6','--text-bright':'#efe8fb','--accent':'#b388ff','--accent2':'#4a9eff'}},
    matrix:  {name:'Matrix', vars:{'--bg':'#020d03','--bg-card':'#06160a','--bg-hover':'#0b2212','--bg-input':'#04100a','--border':'#103a1d','--text':'#b9e8c2','--text-dim':'#4d8f5c','--text-bright':'#dcffe2','--accent':'#20ff66','--accent2':'#36c9ff'}},
    light:   {name:'Светлая', vars:{'--bg':'#eef1f6','--bg-card':'#ffffff','--bg-hover':'#e6ebf3','--bg-input':'#f5f7fa','--border':'#d4dae4','--text':'#22303f','--text-dim':'#5f6f82','--text-bright':'#0b1016','--accent':'#168a5a','--accent2':'#1868c8'}}
  };
  var root = document.documentElement;
  function apply(name){
    var p = PRESETS[name]; if(!p) return;
    for(var k in p.vars) root.style.setProperty(k, p.vars[k]);
    try{ localStorage.setItem('portal_theme', name); }catch(e){}
    var menu = document.getElementById('themeMenu');
    Array.prototype.forEach.call(menu.querySelectorAll('a'), function(a){ a.className = a.getAttribute('data-t') === name ? 'on' : ''; });
  }
  function build(){
    var menu = document.getElementById('themeMenu');
    Object.keys(PRESETS).forEach(function(k){
      var a = document.createElement('a');
      a.textContent = PRESETS[k].name;
      a.setAttribute('data-t', k);
      a.onclick = function(){ apply(k); };
      menu.appendChild(a);
    });
    var saved = 'classic';
    try{ saved = localStorage.getItem('portal_theme') || 'classic'; }catch(e){}
    apply(saved);
  }
  document.addEventListener('DOMContentLoaded', function(){
    build();
    var btn = document.getElementById('themeBtn'), menu = document.getElementById('themeMenu');
    btn.onclick = function(){ menu.style.display = menu.style.display === 'block' ? 'none' : 'block'; };
    document.addEventListener('click', function(e){ if(e.target !== btn && !menu.contains(e.target)) menu.style.display='none'; });
  });
})();
</script>
<?php if ($user && PUSH_ENABLED): ?>
<script>
(function(){ // register the push service worker (only meaningful on https)
  if (!('serviceWorker' in navigator)) return;
  window.addEventListener('load', function(){
    navigator.serviceWorker.register('/sw.js').catch(function(){});
  });
})();
</script>
<?php endif; ?>
<?php if ($user): ?>
<script>
(function(){ // lightweight unread badge + desktop notification while browsing
  if (window.EVEMU_MAIL_OWN_POLL === false) return; // the /mail page runs its own poller
  var SITE = <?= json_encode(SITE_NAME) ?>;
  var lastMail = -1, lastNotif = -1, first = true;
  function poll(){
    fetch('/mail/poll', {credentials:'same-origin'}).then(function(r){ return r.json(); }).then(function(d){
      if (!d.ok) return;
      var dot = document.getElementById('mailBadge');
      if (dot){
        var n = (d.unread|0) + (d.notifications|0);
        dot.style.display = n > 0 ? '' : 'none';
        dot.textContent = n;
      }
      if (!first && (d.unread > lastMail) &&
          typeof Notification !== 'undefined' && Notification.permission === 'granted'){
        try{ new Notification(SITE + ' — ' + (d.unread - lastMail) + ' new mail'); }catch(e){}
      }
      if (!first && (d.notifications > lastNotif) &&
          typeof Notification !== 'undefined' && Notification.permission === 'granted'){
        try{ new Notification(SITE + ' — new notification'); }catch(e){}
      }
      lastMail = d.unread; lastNotif = d.notifications; first = false;
    }).catch(function(){});
  }
  setInterval(poll, 20000);
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
}
