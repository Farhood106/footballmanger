    </div>
    <script>
        // Form submission helper
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const method = (form.method || 'POST').toUpperCase();
                const result = await fetch(form.action, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: method === 'GET' ? undefined : new URLSearchParams(formData).toString()
                }).then(r => r.json());
                
                if (result.error) {
                    alert(result.error);
                } else if (result.redirect) {
                    window.location.href = result.redirect;
                } else if (result.message) {
                    alert(result.message);
                    if (result.reload) location.reload();
                }
            });
        });
    </script>
</body>
</html>
