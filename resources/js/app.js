import {
    Boxes,
    ChartNoAxesCombined,
    CircleHelp,
    ClipboardList,
    DatabaseBackup,
    HardDrive,
    House,
    Inbox,
    Menu,
    Package,
    PackageCheck,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    Tags,
    Truck,
    Users,
    createIcons,
} from 'lucide';
import { initializeAdaraSelects } from './adara-select';
import { initializeCharts, destroyCharts } from './charts';

const icons = {
    Boxes,
    ChartNoAxesCombined,
    CircleHelp,
    ClipboardList,
    DatabaseBackup,
    HardDrive,
    House,
    Inbox,
    Menu,
    Package,
    PackageCheck,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    Tags,
    Truck,
    Users,
};

function initializePage() {
    createIcons({ icons });
    initializeAdaraSelects();
    initializeCharts();
    initializeAuthForm();
}

function initializeAuthForm() {
    const form = document.querySelector('[data-auth-form]');

    if (!form || form.dataset.submissionBound === 'true') {
        return;
    }

    form.dataset.submissionBound = 'true';
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-auth-submit]');
        const label = form.querySelector('[data-auth-label]');
        const spinner = form.querySelector('[data-auth-spinner]');

        if (!button || !label || !spinner) {
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        label.textContent = button.dataset.authLoadingLabel;
        spinner.classList.remove('hidden');
    }, { once: true });
}

initializePage();
document.addEventListener('livewire:navigated', initializePage);
document.addEventListener('livewire:navigating', destroyCharts);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', () => { initializeAdaraSelects(); initializeCharts(); });
    window.Livewire.hook('partial.morphed', () => initializeAdaraSelects());
});
