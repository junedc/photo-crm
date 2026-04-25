import './bootstrap';
import { createApp } from 'vue';
import AdminApp from './admin/AdminApp.vue';
import ClientPortalDesignEditor from './client/ClientPortalDesignEditor.vue';
import { autoAttachGoogleAddressInputs, calculateGoogleAddressDistanceKm } from './googleAddressAutocomplete';

const appElement = document.getElementById('app');

if (appElement && window.adminPage) {
    createApp(AdminApp, {
        page: window.adminPage,
        props: window.adminProps ?? {},
    }).mount(appElement);
}

const clientPortalDesignElement = document.getElementById('client-portal-design-app');

if (clientPortalDesignElement && window.clientPortalDesignProps) {
    createApp(ClientPortalDesignEditor, {
        data: window.clientPortalDesignProps,
    }).mount(clientPortalDesignElement);
}

if (typeof document !== 'undefined') {
    window.calculateGoogleAddressDistanceKm = calculateGoogleAddressDistanceKm;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => autoAttachGoogleAddressInputs());
    } else {
        autoAttachGoogleAddressInputs();
    }
}
