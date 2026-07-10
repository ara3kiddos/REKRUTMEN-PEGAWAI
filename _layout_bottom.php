<!-- ============================================================ -->
<!-- FOOTER -->
<!-- ============================================================ -->
    <footer style="margin-top:40px;padding-top:20px;border-top:1px solid #e5e7eb;text-align:center;color:#6b7280;font-size:13px;">
        <p>© <?= date('Y') ?> Sistem Rekrutmen SDI - Universitas Muhammadiyah Banjarmasin</p>
        <p style="font-size:12px;color:#9ca3af;">
            Login sebagai: <?= htmlspecialchars($role_name ?? '') ?> | 
            ID User: <?= htmlspecialchars($user['id_user'] ?? '') ?>
        </p>
    </footer>
</div>

</body>
</html>