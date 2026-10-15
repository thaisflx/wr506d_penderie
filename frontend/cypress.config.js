import { defineConfig } from 'cypress'

// Tests e2e lancés sur le Mac (npm run test:e2e), contre l'application qui tourne dans Docker.
// Les requêtes de l'application vers l'API passent en environnement test : voir cypress/support/e2e.js.
export default defineConfig({
  e2e: {
    baseUrl: 'http://localhost:5173',
    specPattern: 'cypress/e2e/**/*.cy.js',
    supportFile: 'cypress/support/e2e.js',
    // Application pensée pour le mobile (barre de navigation en bas)
    viewportWidth: 390,
    viewportHeight: 844,
    video: false,
  },
})
