// Langues de l'application : français, anglais, allemand (docs/decisions.md, « Internationalisation »).
// Avant la connexion, la langue est choisie sur l'écran de connexion ; une fois connecté, c'est celle du compte.
const normalize = (text) => text.replace(/\s/g, ' ').trim()

describe('Langue avant la connexion', () => {
  it('passe l\'écran de connexion en anglais, jusqu\'aux erreurs renvoyées par l\'API', () => {
    cy.intercept('POST', '/api/login_check').as('login')
    cy.visit('/login')
    cy.get('html').should('have.attr', 'lang', 'fr')

    cy.getCy('login-locale').select('en')

    cy.get('html').should('have.attr', 'lang', 'en')
    cy.title().should('eq', 'Lendly')
    cy.getCy('login-submit').should('contain.text', 'Sign in')
    cy.getCy('login-email').type('camille@test.local')
    cy.getCy('login-password').type('mauvais')
    cy.getCy('login-submit').click()
    cy.wait('@login').its('request.headers').should('have.property', 'accept-language', 'en')
    cy.getCy('login-error').should('have.text', 'Incorrect email or password.')
  })

  it('garde le choix au rechargement', () => {
    cy.visit('/login')
    cy.getCy('login-locale').select('de')

    cy.reload()

    cy.get('html').should('have.attr', 'lang', 'de')
    cy.getCy('login-submit').should('contain.text', 'Anmelden')
  })
})

describe('Langue du compte', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local', '/profile')
  })

  it('enregistre l\'allemand sur le compte et traduit l\'interface, les pluriels et les dates', () => {
    cy.intercept('PATCH', '/api/me').as('saveLocale')
    cy.getCy('profile-settings').click()

    cy.getCy('settings-locale').select('de')

    cy.wait('@saveLocale').its('request.body').should('deep.equal', { locale: 'de' })
    cy.getCy('settings-locale-saved').should('contain.text', 'Sprache in deinem Konto gespeichert.')
    cy.get('html').should('have.attr', 'lang', 'de')
    cy.get('[data-cy="sheet"]').find('button[aria-label="Schließen"]').click()
    // Pluriel : 14 objets
    cy.contains('.stat', '14').should('contain.text', 'Gegenstände')
    cy.getCy('nav-home').should('contain.text', 'Start').click()
    cy.getCy('feed-title').should('have.text', 'Looks des Tages')
    // Look de Camille publié hier (fixtures test) : date relative en allemand
    cy.getCy('ootd-card').eq(1).find('[data-cy="ootd-when"]').should('have.text', 'gestern')
    cy.getCy('ootd-card').first().find('[data-cy="ootd-relation"]').should('have.text', 'Enger Freund')

    // Rechargé : la langue vient du compte
    cy.reload()
    cy.getCy('feed-title').should('have.text', 'Looks des Tages')
    cy.get('html').should('have.attr', 'lang', 'de')
  })

  it('traduit les catégories proposées par l\'application, pas celles saisies', () => {
    cy.getCy('profile-settings').click()
    cy.getCy('settings-locale').select('en')
    cy.get('[data-cy="sheet"]').find('button[aria-label="Close"]').click()

    cy.getCy('nav-dressing').click()
    cy.contains('[data-cy="room-tile"]', 'Dressing').click()

    // « Hauts » est une catégorie des fixtures (labelKey category.tops) : affichée « Tops »
    cy.getCy('category-name').then((names) => {
      const texts = [...names].map((el) => normalize(el.textContent))
      expect(texts).to.include('Tops')
      expect(texts).not.to.include('Hauts')
    })
    cy.getCy('nav-home').click()
    cy.getCy('ootd-card').eq(1).find('[data-cy="ootd-when"]').should('have.text', 'yesterday')
  })
})
