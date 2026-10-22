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
}

initializePage();
document.addEventListener('livewire:navigated', initializePage);
document.addEventListener('livewire:navigating', destroyCharts);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', () => { initializeAdaraSelects(); initializeCharts(); });
    window.Livewire.hook('partial.morphed', () => initializeAdaraSelects());
});
