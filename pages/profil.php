<?php require_once __DIR__ . '/../includes/header.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('auth-modal');
        if(modal) modal.classList.add('active');
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>