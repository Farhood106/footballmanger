    </div>
    <script>
        // AJAX helper
        async function apiCall(url, method = 'GET', data = null) {
            const options = {
                method,
                headers: { 'Content-Type': 'application/json' }
            };
            if (data && method !== 'GET') {
                options.body = JSON.stringify(data);
            }
            const response = await fetch(url, options);
            return response.json();
        }

        // Form submission helper
        document.querySelectorAll('form[data-ajax]').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);
                const result = await apiCall(form.action, form.method, data);
                
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
