<script setup>
import { computed } from 'vue';
import AdminShell from './components/AdminShell.vue';
import OverviewPage from './pages/OverviewPage.vue';
import PackagesPage from './pages/PackagesPage.vue';
import PackageCreatePage from './pages/PackageCreatePage.vue';
import PackageDetailPage from './pages/PackageDetailPage.vue';
import EquipmentPage from './pages/EquipmentPage.vue';
import EquipmentCreatePage from './pages/EquipmentCreatePage.vue';
import EquipmentDetailPage from './pages/EquipmentDetailPage.vue';
import AddOnsPage from './pages/AddOnsPage.vue';
import AddOnCreatePage from './pages/AddOnCreatePage.vue';
import AddOnDetailPage from './pages/AddOnDetailPage.vue';
import CalendarPage from './pages/CalendarPage.vue';
import BookingsPage from './pages/BookingsPage.vue';
import BookingCreatePage from './pages/BookingCreatePage.vue';
import BookingDetailPage from './pages/BookingDetailPage.vue';
import QuotesPage from './pages/QuotesPage.vue';
import InvoicesPage from './pages/InvoicesPage.vue';
import CustomersPage from './pages/CustomersPage.vue';
import CustomerCreatePage from './pages/CustomerCreatePage.vue';
import CustomerDetailPage from './pages/CustomerDetailPage.vue';
import VendorsPage from './pages/VendorsPage.vue';
import CampaignsPage from './pages/CampaignsPage.vue';
import CampaignCreatePage from './pages/CampaignCreatePage.vue';
import CampaignDetailPage from './pages/CampaignDetailPage.vue';
import EmailTrackingPage from './pages/EmailTrackingPage.vue';
import ExpensesPage from './pages/ExpensesPage.vue';
import TasksPage from './pages/TasksPage.vue';
import DiscountsPage from './pages/DiscountsPage.vue';
import DiscountCreatePage from './pages/DiscountCreatePage.vue';
import DiscountDetailPage from './pages/DiscountDetailPage.vue';
import LeadsPage from './pages/LeadsPage.vue';
import LeadCreatePage from './pages/LeadCreatePage.vue';
import LeadDetailPage from './pages/LeadDetailPage.vue';
import UsersPage from './pages/UsersPage.vue';
import RolesPage from './pages/RolesPage.vue';
import AccessControlPage from './pages/AccessControlPage.vue';
import SettingsPage from './pages/SettingsPage.vue';
import SupportPage from './pages/SupportPage.vue';
import SupportDetailPage from './pages/SupportDetailPage.vue';
import ReferralsPage from './pages/ReferralsPage.vue';

const props = defineProps({
    page: {
        type: String,
        required: true,
    },
    props: {
        type: Object,
        required: true,
    },
});

const pages = {
    overview: OverviewPage,
    packages: PackagesPage,
    'packages-create': PackageCreatePage,
    'packages-detail': PackageDetailPage,
    equipment: EquipmentPage,
    'equipment-create': EquipmentCreatePage,
    'equipment-detail': EquipmentDetailPage,
    addons: AddOnsPage,
    'addons-create': AddOnCreatePage,
    'addons-detail': AddOnDetailPage,
    calendar: CalendarPage,
    bookings: BookingsPage,
    'bookings-create': BookingCreatePage,
    'bookings-detail': BookingDetailPage,
    quotes: QuotesPage,
    invoices: InvoicesPage,
    customers: CustomersPage,
    'customers-create': CustomerCreatePage,
    'customers-detail': CustomerDetailPage,
    vendors: VendorsPage,
    campaigns: CampaignsPage,
    'campaigns-create': CampaignCreatePage,
    'campaigns-detail': CampaignDetailPage,
    'email-tracking': EmailTrackingPage,
    expenses: ExpensesPage,
    tasks: TasksPage,
    discounts: DiscountsPage,
    'discounts-create': DiscountCreatePage,
    'discounts-detail': DiscountDetailPage,
    leads: LeadsPage,
    'leads-create': LeadCreatePage,
    'leads-detail': LeadDetailPage,
    users: UsersPage,
    roles: RolesPage,
    access: AccessControlPage,
    support: SupportPage,
    'support-detail': SupportDetailPage,
    referrals: ReferralsPage,
    settings: SettingsPage,
};

const pageComponent = computed(() => pages[props.page] ?? OverviewPage);
</script>

<template>
    <AdminShell :page="page" :data="props.props">
        <component :is="pageComponent" :data="props.props" />
    </AdminShell>
</template>
