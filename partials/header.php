<header class="site-header">
    <a class="brand" href="/">
        <span class="brand-mark">HO</span>
        <span>
            <strong><?= e($site['name'] ?? 'Hoosier Online') ?></strong>
            <em><?= e($site['tagline'] ?? '') ?></em>
        </span>
    </a>
    <nav>
        <a href="#offer">Offer</a>
        <a href="#contact">Contact</a>
        <a class="admin-link" href="<?= e($site['admin_path'] ?? '/admin.php') ?>">Admin</a>
    </nav>
</header>
