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
}

initializePage();
document.addEventListener('livewire:navigated', initializePage);
