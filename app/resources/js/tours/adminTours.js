export const adminTours = {
    firstAdminLogin: {
        key: 'firstAdminLogin',
        version: 3,
        title: 'Admin rondleiding',
        steps: [
            {
                target: '[data-tour="admin-nav-menu"]',
                title: 'Menukaart beheren',
                body: 'Hier beheer je gerechten, prijzen, omschrijvingen en zichtbaarheid op de menukaart.',
            },
            {
                target: '[data-tour="admin-nav-kassa"]',
                title: 'Kassa',
                body: 'Gebruik de kassa om losse bestellingen samen te stellen en direct af te rekenen.',
            },
            {
                target: '[data-tour="admin-nav-tafels"]',
                title: 'Tafels',
                body: 'Bekijk openstaande tafels, hulpvragen en rekeningen die als PDF kunnen worden opgeslagen.',
            },
            {
                target: '[data-tour="admin-nav-overzicht"]',
                title: 'Overzicht',
                body: 'Controleer verkochte regels en download dagelijkse verkooprapportages.',
            },
            {
                target: '[data-tour="admin-nav-statistieken"]',
                title: 'Statistieken',
                body: 'Volg omzet, populaire gerechten en trends over de gekozen periode.',
            },
        ],
    },
};
