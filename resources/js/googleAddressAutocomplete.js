const GOOGLE_MAPS_SCRIPT_ID = 'google-maps-places-script';

let googleMapsPromise = null;

function getApiKey() {
    return window.googleMapsApiKey || import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
}

function loadGoogleMapsPlaces() {
    if (typeof window === 'undefined') {
        return Promise.resolve(null);
    }

    if (window.google?.maps?.places) {
        return Promise.resolve(window.google);
    }

    if (googleMapsPromise) {
        return googleMapsPromise;
    }

    const apiKey = getApiKey();

    if (!apiKey) {
        return Promise.resolve(null);
    }

    googleMapsPromise = new Promise((resolve, reject) => {
        const existingScript = document.getElementById(GOOGLE_MAPS_SCRIPT_ID);

        if (existingScript) {
            existingScript.addEventListener('load', () => resolve(window.google), { once: true });
            existingScript.addEventListener('error', () => reject(new Error('Google Maps failed to load.')), { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = GOOGLE_MAPS_SCRIPT_ID;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places,geometry&loading=async`;
        script.async = true;
        script.defer = true;
        script.onload = () => resolve(window.google);
        script.onerror = () => reject(new Error('Google Maps failed to load.'));
        document.head.appendChild(script);
    });

    return googleMapsPromise;
}

export async function attachGoogleAddressAutocomplete(input, options = {}) {
    if (!(input instanceof HTMLInputElement) || input.dataset.googleAutocompleteAttached === 'true') {
        return null;
    }

    const google = await loadGoogleMapsPlaces();

    if (!google?.maps?.places?.Autocomplete) {
        return null;
    }

    const autocomplete = new google.maps.places.Autocomplete(input, {
        types: ['address'],
        fields: ['formatted_address', 'name'],
        ...options,
    });

    autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        const nextValue = place?.formatted_address || place?.name;

        if (!nextValue) {
            return;
        }

        input.value = nextValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    input.dataset.googleAutocompleteAttached = 'true';

    return autocomplete;
}

export function autoAttachGoogleAddressInputs(root = document) {
    const inputs = root.querySelectorAll('input[data-google-address="true"]');
    inputs.forEach((input) => {
        attachGoogleAddressAutocomplete(input);
    });
}

export async function calculateGoogleAddressDistanceKm(originAddress, destinationAddress) {
    if (!originAddress || !destinationAddress) {
        return null;
    }

    const google = await loadGoogleMapsPlaces();

    if (google?.maps?.DistanceMatrixService) {
        const distanceKm = await calculateDrivingDistanceKm(google, originAddress, destinationAddress);

        if (distanceKm !== null) {
            return distanceKm;
        }
    }

    return calculateStraightLineDistanceKm(google, originAddress, destinationAddress);
}

async function calculateDrivingDistanceKm(google, originAddress, destinationAddress) {
    const service = new google.maps.DistanceMatrixService();

    try {
        const response = await new Promise((resolve, reject) => {
            service.getDistanceMatrix(
                {
                    origins: [originAddress],
                    destinations: [destinationAddress],
                    travelMode: google.maps.TravelMode.DRIVING,
                    unitSystem: google.maps.UnitSystem.METRIC,
                },
                (result, status) => {
                    if (status !== 'OK') {
                        reject(new Error(`Distance Matrix failed: ${status}`));
                        return;
                    }

                    resolve(result);
                },
            );
        });

        const element = response?.rows?.[0]?.elements?.[0];

        if (element?.status !== 'OK' || !element?.distance?.value) {
            return null;
        }

        return Number((element.distance.value / 1000).toFixed(2));
    } catch {
        return null;
    }
}

async function calculateStraightLineDistanceKm(google, originAddress, destinationAddress) {
    if (!google?.maps?.Geocoder || !google?.maps?.geometry?.spherical?.computeDistanceBetween) {
        return null;
    }

    const geocoder = new google.maps.Geocoder();
    const geocodeAddress = (address) =>
        new Promise((resolve, reject) => {
            geocoder.geocode({ address }, (results, status) => {
                if (status !== 'OK' || !results?.[0]?.geometry?.location) {
                    reject(new Error(`Geocode failed for ${address}`));
                    return;
                }

                resolve(results[0].geometry.location);
            });
        });

    try {
        const [origin, destination] = await Promise.all([
            geocodeAddress(originAddress),
            geocodeAddress(destinationAddress),
        ]);

        const distanceMeters = google.maps.geometry.spherical.computeDistanceBetween(origin, destination);

        return Number((distanceMeters / 1000).toFixed(2));
    } catch {
        return null;
    }
}
