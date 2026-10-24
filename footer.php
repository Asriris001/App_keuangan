<?php
// includes/footer.php
$flash = get_flash();
?>
    </div> <!-- Close main content container -->
</div> <!-- Close flex-1 content area -->

<!-- Custom JS -->
<script src="assets/js/app.js"></script>

<?php if ($flash): ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        Swal.fire({
            icon: '<?= $flash['type'] ?>',
            title: '<?= addslashes($flash['title']) ?>',
            text: '<?= addslashes($flash['text']) ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            customClass: {
                popup: 'rounded-xl shadow-lg border border-slate-100'
            }
        });
    });
</script>
<?php endif; ?>

</body>
</html>

