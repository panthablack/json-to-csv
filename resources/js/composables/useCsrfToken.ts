/**
 * Composable for managing CSRF token renewal
 */
export function useCsrfToken() {
    /**
     * Renew the CSRF token by fetching a fresh token from the server
     */
    const renewToken = async (): Promise<void> => {
        try {
            // Make a request to get a fresh CSRF token
            const response = await fetch('/csrf-token', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                if (data.csrf_token) {
                    // Update the current page's CSRF token meta tag
                    const currentMeta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
                    if (currentMeta) {
                        currentMeta.content = data.csrf_token;
                    }
                }
            }
        } catch (error) {
            console.error('Failed to renew CSRF token:', error);
            // Fallback: force page reload to get fresh token
            window.location.reload();
        }
    };

    return {
        renewToken,
    };
}