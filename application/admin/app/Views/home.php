<?php use App\Security; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=Security::esc(getenv('APP_NAME')?:'My App')?></title><link rel="stylesheet" href="/assets/<?=$settings ? 'builder' : 'welcome'?>.css?v=monochrome-1"><script src="/assets/home.js" defer></script></head>
<body class="home" data-admin="<?=Security::esc($adminPath)?>">
<?php if (!$settings): ?>
<main class="welcome">
  <div class="welcome-card">
    <p class="brand">App Studio</p>
    <h1>Welcome to your app</h1>
    <p class="description">Set up your admin account first. Then connect Claude or OpenAI and start building through chat.</p>
    <a class="button" href="/admin">Set up admin account</a>
    <p class="hint">No coding experience needed.</p>
  </div>
</main>
<?php else: ?>
<iframe class="website-frame" title="Your website" src="/_site/" sandbox="allow-scripts allow-forms allow-popups"></iframe>
<?php if($signedIn && (int)$settings['floating']===1): ?>
<button class="floating-button" id="open-chat" aria-haspopup="dialog">✦ Build with AI</button>
<dialog id="floating-chat" class="floating-dialog"><div class="popup-bar"><strong>Build with AI</strong><button id="close-chat" class="quiet" aria-label="Close chat">×</button></div><iframe title="Private AI building chat" src="<?=Security::esc($adminPath)?>?embed=1"></iframe></dialog>
<?php endif; ?>
<?php endif; ?>
</body></html>
