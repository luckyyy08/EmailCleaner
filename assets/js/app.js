document.addEventListener('DOMContentLoaded', () => {
    // Theme toggle
    const themeToggleBtn = document.getElementById('theme-toggle');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            const icon = themeToggleBtn.querySelector('i');
            if (newTheme === 'dark') {
                icon.classList.remove('bi-moon-fill'); icon.classList.add('bi-sun-fill');
            } else {
                icon.classList.remove('bi-sun-fill'); icon.classList.add('bi-moon-fill');
            }
        });
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            const icon = themeToggleBtn.querySelector('i');
            if (savedTheme === 'dark') {
                icon.classList.remove('bi-moon-fill'); icon.classList.add('bi-sun-fill');
            } else {
                icon.classList.remove('bi-sun-fill'); icon.classList.add('bi-moon-fill');
            }
        }
    }

    // Helper for modern alerts
    const showAlert = (title, text, icon = 'info') => {
        Swal.fire({
            title: title, text: text, icon: icon,
            confirmButtonColor: '#0d6efd',
            background: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#212529' : '#fff',
            color: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#fff' : '#000'
        });
    };

    const showToast = (title, icon = 'success') => {
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true,
            background: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#212529' : '#fff',
            color: document.documentElement.getAttribute('data-bs-theme') === 'dark' ? '#fff' : '#000'
        });
        Toast.fire({ icon: icon, title: title });
    };

    // Scan Logic
    const scanBtn = document.getElementById('scan-inbox-btn');
    const sidebarScanBtn = document.getElementById('sidebar-scan-btn');
    
    const performScan = async (btn) => {
        const limitEl = document.getElementById('scan-limit');
        if (!limitEl) return;
        const limit = limitEl.value;
        const categories = [];
        if(document.getElementById('cat-promos').checked) categories.push('promotions');
        if(document.getElementById('cat-social').checked) categories.push('social');
        if(document.getElementById('cat-spam').checked) categories.push('spam');

        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Scanning...`;
        btn.disabled = true;

        const progContainer = document.getElementById('scan-progress-container');
        const progBar = document.getElementById('scan-progress-bar');
        const progText = document.getElementById('scan-status-text');
        if (progContainer) { progContainer.classList.remove('d-none'); progBar.style.width = '0%'; }

        let pollInterval = setInterval(async () => {
            const res = await fetch('api/scan_status.php');
            const statusData = await res.json();
            if (statusData.success && statusData.status === 'scanning') {
                progBar.style.width = statusData.percent + '%';
                progText.innerText = `Scanning ${statusData.current} of ${statusData.total == 0 ? 'all' : statusData.total}...`;
            }
        }, 1000);

        try {
            const formData = new FormData();
            formData.append('limit', limit);
            categories.forEach(cat => formData.append('categories[]', cat));
            const response = await fetch('api/scan.php', { method: 'POST', body: formData });
            const data = await response.json();
            clearInterval(pollInterval);
            if (data.success) {
                showToast(`Scan complete! Found ${data.scanned} emails.`);
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showAlert('Error', data.message, 'error');
                btn.innerHTML = originalHtml; btn.disabled = false;
            }
        } catch (e) { clearInterval(pollInterval); showAlert('Error', 'Scan failed', 'error'); btn.innerHTML = originalHtml; btn.disabled = false; }
    };

    if (scanBtn) scanBtn.addEventListener('click', () => performScan(scanBtn));
    if (sidebarScanBtn) sidebarScanBtn.addEventListener('click', () => performScan(sidebarScanBtn));

    // Global Click Handler (Event Delegation)
    document.addEventListener('click', async (e) => {
        const target = e.target;
        const actionBtn = target.closest('.action-btn');
        const deleteSingleBtn = target.closest('.delete-single');
        const bulkDeleteBtn = target.closest('#bulk-delete-btn');
        const selectAll = target.closest('#selectAll');
        const emptySpamBtn = target.closest('#empty-spam-btn');

        // 0. Empty Spam Folder
        if (emptySpamBtn) {
            const result = await Swal.fire({
                title: 'Empty Spam?',
                text: 'Permanently move ALL spam emails to trash? This can take a moment.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Empty Folder'
            });

            if (result.isConfirmed) {
                const originalHtml = emptySpamBtn.innerHTML;
                emptySpamBtn.disabled = true;
                emptySpamBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Emptying...';
                
                try {
                    const res = await fetch('api/delete.php?action=empty_spam');
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message);
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showAlert('Error', data.message, 'error');
                        emptySpamBtn.disabled = false;
                        emptySpamBtn.innerHTML = originalHtml;
                    }
                } catch (e) {
                    showAlert('Error', 'Failed to empty spam', 'error');
                    emptySpamBtn.disabled = false;
                    emptySpamBtn.innerHTML = originalHtml;
                }
            }
        }

        // 1. Select All Checkboxes
        if (selectAll) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        // 2. Action Buttons (Smart Cleanup)
        if (actionBtn) {
            const action = actionBtn.getAttribute('data-action');
            const sender = actionBtn.getAttribute('data-sender');
            
            const result = await Swal.fire({
                title: 'Confirm Action',
                text: 'Are you sure you want to perform this cleanup?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, proceed'
            });

            if (result.isConfirmed) {
                actionBtn.disabled = true;
                const url = `api/delete.php?action=${action}${sender ? '&sender='+sender : ''}`;
                const res = await fetch(url);
                const data = await res.json();
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('Error', data.message, 'error');
                    actionBtn.disabled = false;
                }
            }
        }

        // 3. Delete Single Email
        if (deleteSingleBtn) {
            const id = deleteSingleBtn.getAttribute('data-id');
            const result = await Swal.fire({
                title: 'Delete Email?',
                text: 'Move this email to trash?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Delete'
            });

            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('ids[]', id);
                formData.append('action', 'bulk_delete');
                const res = await fetch('api/delete.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast('Deleted');
                    const row = deleteSingleBtn.closest('tr');
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
            }
        }

        // 4. Bulk Delete
        if (bulkDeleteBtn && !bulkDeleteBtn.id.includes('scan')) {
            const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
            if (selected.length === 0) { showAlert('Selection Required', 'Select emails first', 'info'); return; }
            
            const result = await Swal.fire({
                title: 'Bulk Delete',
                text: `Delete ${selected.length} emails?`,
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete All'
            });

            if (result.isConfirmed) {
                bulkDeleteBtn.disabled = true;
                const formData = new FormData();
                selected.forEach(id => formData.append('ids[]', id));
                formData.append('action', 'bulk_delete');
                const res = await fetch('api/delete.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) { showToast(data.message); setTimeout(() => window.location.reload(), 1500); }
                else { bulkDeleteBtn.disabled = false; }
            }
        }
    });

    // Share Button
    const shareBtn = document.getElementById('share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', () => {
            const saved = document.getElementById('stat-saved').innerText;
            const text = `I just cleaned my inbox using CleanBox AI and saved ${saved}! Try it now.`;
            navigator.clipboard.writeText(text).then(() => showToast('Stats copied!', 'success'));
        });
    }
});
