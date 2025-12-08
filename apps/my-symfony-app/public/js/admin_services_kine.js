document.addEventListener('DOMContentLoaded', function () {
    // Ouvrir le modal en mode modification
    document.querySelectorAll('.zone-kine-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-id');
            fetch('/admin/service/' + id, { method: 'GET' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('serviceModalLabel').textContent = 'Modifier un service';
                        document.getElementById('serviceId').value = id;
                        document.getElementById('serviceName').value = data.service.name;
                        document.getElementById('serviceCategory').value = data.service.categorie.id;
                        document.getElementById('servicePrice').value = data.service.price;
                        const modal = new bootstrap.Modal(document.getElementById('serviceModal'));
                        modal.show();
                    } else {
                        alert('Service non trouvé');
                    }
                });
        });
    });

    // Suppression d'un service kiné
    document.querySelectorAll('.zone-kine-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Voulez-vous vraiment supprimer ce service ?')) return;
            const id = btn.getAttribute('data-id');
            const url = '/admin/service/delete/' + id;
            console.log('Suppression service kiné, appel URL :', url);
            fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Retirer la ligne du tableau
                        btn.closest('tr').remove();
                    } else {
                        alert(data.message || 'Erreur lors de la suppression');
                    }
                });
        });
    });
});
