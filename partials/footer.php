<footer class="site-footer">
    <span>&copy; <?= date('Y') ?> <?= e($site['name'] ?? 'Hoosier Online') ?></span>
    <a href="<?= e($site['admin_path'] ?? '/admin.php') ?>">Admin</a>
</footer>
