//
import Alpine from 'alpinejs';
import transactionForm from './transaction-form';

window.Alpine = Alpine;
Alpine.data('transactionForm', transactionForm);
Alpine.start();
