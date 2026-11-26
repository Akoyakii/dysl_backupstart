<?php
require __DIR__ . '/includes/security.php';
require __DIR__ . '/includes/audit.php';
startRequest(loadGoogleFonts: true);

$config = [
    'brand_name' => "Dr. Yanga's Colleges, Inc.",
    'brand_short' => 'DYSL',
    'location' => 'Bocaue, Bulacan',
    'motto_sub' => 'Manage student scholars, scholarships, and discount history.',
    'logo_path' => 'pics/logo.png',
];

$signup = isset($_GET['signup']);
$activation = !$signup && isset($_GET['activate']);
$resetTemporary = !$signup && !$activation && isset($_GET['temporary']);
$newPassword = $signup || $activation || $resetTemporary;
$pageQuery = $signup ? '?signup=1' : ($activation ? '?activate=1' : ($resetTemporary ? '?temporary=1' : ''));
$login_error = null;
$login_notice = null;
$username = '';
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'POST'], true)) {
    stopRequest(405, 'Method not allowed.');
}
if ($method === 'GET' && isset($_GET['waiting'])) {
    require __DIR__ . '/views/waiting-approval.php';
    exit;
}

try {
    $db = recordsDatabase();
    if (!requestLimit($db, 'login', 60, 60)) {
        stopRequest(429, 'Too many requests. Try again shortly.');
    }
    if (currentAccount($db)) {
        header('Location: index');
        exit;
    }
    if ($resetTemporary) {
        $grant = $_SESSION['temporary_reset'] ?? null;
        if (!$grant || $grant['expires'] <= time()) {
            unset($_SESSION['temporary_reset']);
            header('Location: login', true, 303);
            exit;
        }
        if ($method === 'POST') {
            if (!validCsrf($_POST['csrf'] ?? null)) stopRequest(403, 'Reload the page and try again.');
            if (!requestLimit($db, 'temporary-reset', 10, 300, (string) $grant['id'])) {
                stopRequest(429, 'Too many attempts. Try again shortly.');
            }
            $password = $_POST['password'] ?? null;
            if (!is_string($password) || strlen($password) > 1024 || !validNewPassword($password)) {
                $login_error = NEW_PASSWORD_REQUIREMENTS;
            } elseif ($password !== ($_POST['password_confirmation'] ?? null)) {
                $login_error = 'The passwords do not match.';
            } else {
                $lookup = $db->prepare("SELECT password_hash FROM accounts WHERE id = ? AND role = 'staff'
                    AND access_version = ? AND disabled_at IS NULL AND temporary_password_expires > UTC_TIMESTAMP()
                    AND (SELECT session_epoch FROM record_write_lock WHERE id = 1) = ?");
                $lookup->execute([$grant['id'], $grant['version'], $grant['epoch'] ?? 0]);
                $hash = $lookup->fetchColumn();
                if ($hash && password_verify($password, $hash)) {
                    $login_error = 'Choose a password different from the temporary password.';
                } else {
                    $save = $db->prepare("UPDATE accounts SET password_hash = ?, temporary_password_expires = NULL,
                        access_version = access_version + 1 WHERE id = ? AND role = 'staff' AND access_version = ?
                        AND disabled_at IS NULL AND temporary_password_expires > UTC_TIMESTAMP()
                        AND (SELECT session_epoch FROM record_write_lock WHERE id = 1) = ?");
                    if ($hash) $save->execute([password_hash($password, PASSWORD_ARGON2ID), $grant['id'], $grant['version'], $grant['epoch'] ?? 0]);
                    unset($_SESSION['temporary_reset']);
                    if (!$hash || $save->rowCount() !== 1) stopRequest(403, 'This temporary password is no longer valid. Return to sign in.');
                    session_regenerate_id(true);
                    $_SESSION = ['password_changed' => true];
                    writeAccessAuditEvent($db, ['id' => $grant['id'], 'role' => 'staff'], 'account.temporary_password_changed');
                    header('Location: login', true, 303);
                    exit;
                }
            }
        }
    } elseif ($method === 'POST') {
        if (!validCsrf($_POST['csrf'] ?? null)) {
            stopRequest(403, 'Reload the page and try again.');
        }
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = $_POST['password'] ?? null;
        $sendingCode = ($signup || $activation) && ($_POST['action'] ?? null) === 'send-code';
        $flow = $sendingCode ? 'send-code' : ($signup ? 'signup' : ($activation ? 'activation' : 'sign-in'));
        if (!requestLimit($db, $flow . '-ip', 50, 300) ||
            (!$sendingCode && validAccountEmail($username, $signup || $activation) &&
                !requestLimit($db, $flow . '-account', 10, 300, strtolower($username)))) {
            stopRequest(429, 'Too many attempts. Try again shortly.');
        }
        if ($sendingCode) {
            if (!validAccountEmail($username, true)) {
                $login_error = 'Enter a valid @gmail.com address.';
            } else {
                $login_notice = requestActivationCode($db, $username, $signup);
            }
        } elseif (!validAccountEmail($username, $activation || $signup)) {
            $login_error = $activation || $signup ? 'Enter a valid @gmail.com address.' : 'Enter a valid email address.';
        } elseif (!is_string($password) || strlen($password) > 1024 || $password === '') {
            $login_error = 'Enter a valid password.';
        } elseif (($signup || $activation) && !validNewPassword($password)) {
            $login_error = NEW_PASSWORD_REQUIREMENTS;
        } elseif (($signup || $activation) && $password !== ($_POST['password_confirmation'] ?? null)) {
            $login_error = 'The passwords do not match.';
        } elseif ($signup) {
            if (completeSignup($db, $username, $password, $_POST['invitation'] ?? null)) {
                header('Location: waiting-for-approval', true, 303);
                exit;
            } else {
                $login_error = 'The signup code is invalid or expired, or the account already exists.';
            }
        } else {
            unset($_SESSION['temporary_reset']);
            $lookup = $db->prepare('SELECT id, role, password_hash, invitation_hash, invitation_expires, verified_at, disabled_at, access_version,
                temporary_password_expires, temporary_password_expires > UTC_TIMESTAMP() AS temporary_valid,
                (SELECT session_epoch FROM record_write_lock WHERE id = 1) AS session_epoch FROM accounts WHERE email = ?');
            $lookup->execute([$username]);
            $account = $lookup->fetch(PDO::FETCH_ASSOC);
            $signedIn = false;
            if ($activation) {
                $code = $_POST['invitation'] ?? null;
                if ($account && $account['disabled_at'] === null && verifyActivationCode($db, $account, $code)) {
                    $activate = $db->prepare('UPDATE accounts SET password_hash = ?, verified_at = UTC_TIMESTAMP(),
                        invitation_hash = NULL, invitation_expires = NULL
                        WHERE id = ? AND verified_at IS NULL AND disabled_at IS NULL AND invitation_hash = ? AND invitation_expires > ?');
                    $activate->execute([password_hash($password, PASSWORD_ARGON2ID), $account['id'], $account['invitation_hash'], time()]);
                    $signedIn = $activate->rowCount() === 1;
                }
            } else {
                $dummyHash = '$argon2id$v=19$m=65536,t=4,p=1$OTJYbUpyMDE2Uk9rRldYcw$4EyR6OjYc/mbY+fHDAWeLb4v1Dz8nAraBrzacBNuxL4';
                $matches = password_verify($password, $account['password_hash'] ?? $dummyHash);
                $signedIn = $matches && $account && $account['verified_at'] !== null && $account['disabled_at'] === null;
                if ($signedIn && $account['role'] !== 'admin') {
                    $pending = $db->prepare('SELECT 1 FROM signup_requests WHERE email = ?');
                    $pending->execute([$username]);
                    if ($pending->fetchColumn()) {
                        header('Location: waiting-for-approval', true, 303);
                        exit;
                    }
                }
            }
            if ($signedIn) {
                if ($account['temporary_password_expires'] !== null) {
                    if (!$account['temporary_valid'] || $account['role'] !== 'staff') {
                        stopRequest(403, 'This temporary password has expired. Ask your administrator for a new one.');
                    }
                    session_regenerate_id(true);
                    $_SESSION = ['temporary_reset' => ['id' => $account['id'], 'version' => $account['access_version'],
                        'epoch' => $account['session_epoch'], 'expires' => time() + 300]];
                    header('Location: login?temporary=1', true, 303);
                    exit;
                }
                session_regenerate_id(true);
                $_SESSION = ['account_id' => $account['id'], 'signed_in_at' => time(), 'access_version' => $account['access_version'],
                    'session_epoch' => $account['session_epoch']];
                if ($activation) {
                    writeSessionAuditEvent($db, $account, 'account.activated');
                }
                writeSessionAuditEvent($db, $account, 'account.signed_in');
                header('Location: index', true, 303);
                exit;
            }
            if (!$activation && $login_error === null) {
                writeAccessAuditEvent($db, ['id' => 0, 'role' => 'viewer'], 'account.sign_in_failed');
            }
            $login_error ??= $activation ? 'The invitation code is invalid or expired.' : 'The email or password is incorrect.';
        }
    } elseif (isset($_SESSION['database_restored'])) {
        unset($_SESSION['database_restored']);
        $login_notice = 'Database restored. Sign in again to continue.';
    } elseif (isset($_SESSION['password_changed'])) {
        unset($_SESSION['password_changed']);
        $login_notice = 'Password changed. Sign in with your new password.';
    }
} catch (Throwable $error) {
    stopRequest(503, databaseSetupMessage($error) ?? 'Sign-in is temporarily unavailable.');
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$currentYear = date('Y');
?>
<!DOCTYPE html>
<html class="h-full" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?= e($config['brand_short']) ?> Portal | <?= e($config['brand_name']) ?></title>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&amp;family=Playfair+Display:ital,wght@0,600;0,700;0,800;1,600&amp;family=Plus+Jakarta+Sans:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script nonce="<?= cspNonce() ?>" src="https://cdn.tailwindcss.com/3.4.17?plugins=forms,container-queries"></script>
    <script nonce="<?= cspNonce() ?>" data-purpose="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dyci: {
                            navy: '#050B1E',
                            deep: '#030712',
                            midnight: '#0A132C',
                            gold: '#F4B925',
                            goldHover: '#E0A619',
                            goldInk: '#8A6100',
                            goldLight: '#FDE68A',
                            card: '#FFFFFF',
                            border: 'rgba(244, 185, 37, 0.35)'
                        }
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'Cinzel', 'Georgia', 'serif'],
                        cinzel: ['Cinzel', 'serif'],
                        sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif']
                    },
                    boxShadow: {
                        goldGlow: '0 12px 30px -14px rgba(244, 185, 37, 0.75)',
                        deepCard: '0 24px 60px -30px rgba(5, 11, 30, 0.35)'
                    }
                }
            }
        };
    </script>
    <style data-purpose="typography">
        .font-cinzel { font-family: 'Cinzel', serif; }
        .letter-spacing-widest { letter-spacing: 0.22em; }
    </style>
    <style data-purpose="card-glass-fx">
        .glass-portal-panel {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.97) 0%, rgba(255, 253, 247, 0.92) 100%);
            border: 1px solid rgba(244, 185, 37, 0.38);
        }
        .subtle-grid {
            background-size: 40px 40px;
            background-image:
                linear-gradient(to right, rgba(5, 11, 30, 0.035) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(5, 11, 30, 0.035) 1px, transparent 1px);
        }
    </style>
</head>

<body class="h-full bg-white text-slate-800 font-sans antialiased overflow-x-hidden selection:bg-dyci-gold selection:text-dyci-deep flex flex-col justify-between relative">

<div class="fixed inset-0 pointer-events-none z-0 overflow-hidden bg-white" data-purpose="background-container">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_12%_8%,rgba(244,185,37,0.14),transparent_48%),radial-gradient(ellipse_at_88%_18%,rgba(5,11,30,0.05),transparent_42%)]"></div>
    <div class="absolute inset-0 subtle-grid opacity-70"></div>
</div>

<header class="relative z-20 w-full border-b border-slate-200/80 bg-white/80 backdrop-blur-md" data-purpose="top-navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        <div class="flex items-center space-x-3.5">
            <div class="w-11 h-11 flex-shrink-0">
                <img alt="<?= e($config['brand_name']) ?> institutional seal" class="w-full h-full object-contain" data-brand-logo src="<?= e($config['logo_path']) ?>"/>
            </div>
            <div>
                <span class="block text-xs uppercase font-cinzel tracking-wider text-dyci-goldInk font-bold"><?= e($config['brand_name']) ?></span>
                <span class="block text-xs text-slate-500 tracking-tight font-medium">Institutional Portal • <?= e($config['location']) ?></span>
            </div>
        </div>
    </div>
</header>

<main class="relative z-10 flex-grow flex items-center justify-center py-8 lg:py-12 px-4 sm:px-6 lg:px-8" data-purpose="portal-hero-container">
    <div class="max-w-7xl w-full mx-auto grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-8 items-center">
        <section class="lg:col-span-7 flex flex-col justify-center text-left space-y-6" data-purpose="hero-brand-section">
            <div class="space-y-3">
                <h1 class="text-balance text-4xl sm:text-6xl font-serif font-bold text-slate-900 tracking-tight leading-[1.08] drop-shadow-sm">
                    Scholarship records
                </h1>
                <div class="pt-2">
                    <div class="w-24 sm:w-32 h-[3px] bg-dyci-gold mt-2 rounded-full"></div>
                    <p class="text-slate-600 text-sm mt-3 max-w-lg leading-relaxed font-light">
                        <?= e($config['motto_sub']) ?>
                    </p>
                </div>
            </div>
        </section>

        <section class="lg:col-span-5 w-full" data-purpose="portal-login-card">
            <div class="glass-portal-panel rounded-2xl p-8 sm:p-9 shadow-deepCard relative overflow-hidden w-full max-w-[400px] min-h-[470px] mx-auto flex flex-col justify-center">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-dyci-gold via-yellow-400 to-dyci-gold/20"></div>
                <div class="mb-8">
                    <h2 class="text-2xl font-serif font-bold text-slate-900 tracking-tight"><?= $signup ? 'Sign up' : ($activation ? 'Activate your account' : ($resetTemporary ? 'Set a new password' : 'Sign in')) ?></h2>
                    <p class="text-xs text-slate-500 mt-2 leading-relaxed"><?= $signup ? 'Verify your Gmail address and set a password.' : ($activation ? 'Use this form if an administrator invited you directly.' : ($resetTemporary ? 'Replace your temporary password to continue.' : 'Use your verified ScholarHub account.')) ?></p>
                </div>

                <?php if ($login_error !== null): ?>
                <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700" role="alert">
                    <?= e($login_error) ?>
                </div>
                <?php endif; ?>
                <?php if ($login_notice !== null): ?>
                <p role="status" class="mb-5 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-800"><?= e($login_notice) ?></p>
                <?php endif; ?>

                <form action="login<?= $pageQuery ?>" class="space-y-5" method="POST">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <?php if (!$resetTemporary): ?>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2" for="username"><?= $signup || $activation ? 'Gmail address' : 'Email address' ?></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                </svg>
                            </div>
                            <input class="w-full pl-10 pr-3 py-3 bg-white border border-slate-300 focus:border-dyci-gold focus:ring-1 focus:ring-dyci-gold rounded-lg text-sm text-slate-900 placeholder-slate-400 transition" autocomplete="username" id="username" name="username" placeholder="yourname@gmail.com" required type="email" maxlength="254" value="<?= e($username) ?>"/>
                        </div>
                    </div>

                    <?php endif; ?>
                    <?php if ($activation || $signup): ?>
                    <div>
                        <button type="submit" name="action" value="send-code" formnovalidate class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-100">Send verification code</button>
                        <label class="mb-2 mt-4 block text-sm font-semibold" for="invitation">Email verification code</label>
                        <input id="invitation" name="invitation" autocomplete="one-time-code" pattern="<?= $signup ? '[0-9]{6}' : '([0-9]{6}|[a-f0-9]{48})' ?>" maxlength="<?= $signup ? 6 : 48 ?>" required class="w-full rounded-lg border-slate-300">
                        <p class="mt-2 text-sm text-slate-600"><?= $signup ? 'Enter the six-digit code from your email.' : 'Enter the six-digit email code or the manual invitation code from your administrator.' ?></p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2" for="password-input">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                                </svg>
                            </div>
                            <!-- id="password-input" added -->
                            <input class="w-full pl-10 pr-16 py-3 bg-white border border-slate-300 focus:border-dyci-gold focus:ring-1 focus:ring-dyci-gold rounded-lg text-sm text-slate-900 placeholder-slate-400 transition" autocomplete="<?= $newPassword ? 'new-password' : 'current-password' ?>" maxlength="1024" id="password-input" name="password" placeholder="••••••••" <?= $newPassword ? 'minlength="' . MIN_NEW_PASSWORD_LENGTH . '" aria-describedby="password-requirements"' : '' ?> required type="password"/>
                            
                            <button type="button" data-password-toggle aria-controls="password-input" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-dyci-blue hover:underline">Show</button>
                        </div>
                        <?php if ($newPassword): ?>
                        <p id="password-requirements" class="mt-2 text-sm text-slate-600"><?= e(NEW_PASSWORD_REQUIREMENTS) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($newPassword): ?>
                    <div>
                        <label class="mb-2 block text-sm font-semibold" for="password-confirmation">Confirm password</label>
                        <div class="relative">
                            <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="<?= MIN_NEW_PASSWORD_LENGTH ?>" maxlength="1024" required class="w-full rounded-lg border-slate-300 py-3 pr-16 text-sm">
                            <button type="button" data-password-toggle aria-controls="password-confirmation" aria-label="Show password confirmation" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-dyci-blue hover:underline">Show</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <button class="w-full py-3.5 px-4 rounded-lg bg-dyci-gold hover:bg-dyci-goldHover text-dyci-deep font-bold text-sm tracking-wide uppercase shadow-goldGlow transition-all duration-200 transform active:scale-[0.99] flex items-center justify-center space-x-2 mt-1" type="submit" name="action" value="activate">
                        <span><?= $signup ? 'Create account' : ($activation ? 'Activate account' : ($resetTemporary ? 'Save new password' : 'Sign in')) ?></span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"></path>
                        </svg>
                    </button>
                </form>
                <nav class="mt-4 flex flex-wrap gap-4 text-sm font-semibold text-dyci-blue" aria-label="Account">
                    <?php if ($newPassword): ?><a href="login">Sign in</a><?php endif; ?>
                    <?php if (!$signup && !$resetTemporary): ?><a href="login?signup=1">Sign up</a><?php endif; ?>
                </nav>
            </div>
        </section>
    </div>
</main>

<footer class="relative z-20 border-t border-slate-200 bg-white/90 py-4 text-xs text-slate-500 backdrop-blur-sm" data-purpose="site-footer">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2 text-center sm:text-left">
        <div>
            <span class="text-slate-700 font-medium"><?= e($config['brand_name']) ?></span> • Wakas, <?= e($config['location']) ?> •
        </div>
        <div class="flex items-center space-x-4 text-[11px]">
            <a class="hover:text-slate-900 transition" href="#privacy">Data Privacy</a>
            <span>•</span>
            <a class="hover:text-slate-900 transition" href="#terms">Acceptable Use Policy</a>
            <span>•</span>
            <span>© <?= e($currentYear) ?> <?= e($config['brand_short']) ?>. All rights reserved.</span>
        </div>
    </div>
</footer>

<!-- SCRIPT FOR TOGGLE PASSWORD -->
<script nonce="<?= cspNonce() ?>">
    document.querySelectorAll("[data-password-toggle]").forEach(button => {
        const input = document.getElementById(button.getAttribute("aria-controls"));
        const field = input.id === "password-confirmation" ? "password confirmation" : "password";
        button.addEventListener("click", () => {
            const show = input.type === "password";
            input.type = show ? "text" : "password";
            button.textContent = show ? "Hide" : "Show";
            button.setAttribute("aria-label", button.textContent + " " + field);
        });
    });
</script>

</body>
</html>
