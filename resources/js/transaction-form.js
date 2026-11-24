export default function transactionForm(config) {
    return {
        type: config.type,
        category: config.category,
        categories: config.categories,
        merchant: config.merchant,
        suggestUrl: config.suggestUrl,
        hint: '',
        touched: false,

        init() {
            this.$watch('type', () => {
                this.category = '';
                this.hint = '';
                this.touched = false;
                this.suggest();
            });
        },

        pick() {
            this.touched = true;
            this.hint = '';
        },

        async suggest() {
            const merchant = this.merchant.trim();
            const autoFilled = this.hint !== '';

            if (this.touched || this.type === 'transfer' || merchant.length < 2) {
                return;
            }

            if (this.category !== '' && !autoFilled) {
                return;
            }

            try {
                const url = new URL(this.suggestUrl, window.location.origin);
                url.searchParams.set('merchant', merchant);
                url.searchParams.set('type', this.type);

                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                if (data.category_id) {
                    this.category = String(data.category_id);
                    this.hint =
                        (data.source === 'rule' ? 'Suggested by your rule: ' : 'Suggested from your history: ') +
                        data.category_name;
                } else if (autoFilled) {
                    this.category = '';
                    this.hint = '';
                }
            } catch (error) {
                // Suggestions are optional; ignore network errors.
            }
        },
    };
}
