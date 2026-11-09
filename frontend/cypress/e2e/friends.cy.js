// Volet social : page Amis, recherche, profil public, blocage (docs/decisions.md).
// Fixtures test : Nina a une demande d'ami proche en attente chez Camille ; Théo et Marc sont ses abonnés ;
// Sacha et Lou sont ses comptes enfants.

const texts = (name) => cy.getCy(name).then((elements) => [...elements].map((element) => element.textContent.trim()))

function searchAndOpen(query, username) {
  cy.getCy('nav-friends').click()
  cy.getCy('friends-tab-search').click()
  cy.getCy('friends-search').type(query)
  cy.contains('[data-cy="search-result"]', username).click()
  cy.getCy('public-username').should('have.text', username)
}

describe('Demandes reçues', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
  })

  it('accepte la demande de Nina depuis la cloche', () => {
    cy.getCy('home-bell-count').should('have.text', '1')
    cy.getCy('home-bell').click()

    cy.location('pathname').should('eq', '/friends')
    cy.getCy('request-name').should('have.text', 'nina')
    cy.getCy('request-type').should('contain.text', 'Ami proche')
    cy.getCy('request-accept').click()

    cy.getCy('requests-empty').should('be.visible')
    cy.getCy('friends-tab-relations').click()
    texts('relation-name').should('include', 'nina')
    // Plus aucune demande : le badge de la cloche disparaît
    cy.getCy('nav-home').click()
    cy.getCy('home-bell-count').should('not.exist')
  })

  it('refuse la demande de Nina', () => {
    cy.getCy('nav-friends').click()
    cy.getCy('request-refuse').click()

    cy.getCy('requests-empty').should('be.visible')
    cy.getCy('friends-tab-relations').click()
    texts('relation-name').should('not.include', 'nina')
  })

  it('ouvre la page Amis depuis le bandeau du profil', () => {
    cy.getCy('nav-profile').click()
    cy.getCy('profile-requests-notice').should('contain.text', '1 demande en attente').click()

    cy.location('pathname').should('eq', '/friends')
  })
})

describe('Recherche et profil public', () => {
  it('Zoé cherche Camille, la suit, puis annule sa demande', () => {
    cy.loginAs('zoe@test.local')
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-search').click()
    cy.getCy('friends-search').type('c')
    cy.contains('Tape au moins 2 caractères du pseudo.')
    cy.getCy('friends-search').type('am')
    texts('search-name').should('deep.equal', ['camille'])

    cy.getCy('search-result').click()
    cy.getCy('public-username').should('have.text', 'camille')
    cy.getCy('follow').should('contain.text', 'Suivre').click()
    cy.getCy('follow').should('contain.text', 'Demande envoyée')

    // La demande apparaît dans « Mes abonnements », en attente
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-relations').click()
    cy.getCy('relations-sent').contains('[data-cy="relation-row"]', 'camille').should('contain.text', 'En attente')

    // Annulation depuis le profil
    cy.getCy('relations-sent').contains('[data-cy="relation-name"]', 'camille').click()
    cy.getCy('follow').click()
    cy.getCy('confirm-remove').click()
    cy.getCy('follow').should('contain.text', 'Suivre')
  })

  it('un compte -18 n\'apparaît pas dans la recherche', () => {
    cy.loginAs('theo@test.local')
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-search').click()
    cy.getCy('friends-search').type('lou')

    cy.getCy('search-empty').should('be.visible')
  })

  it('un profil introuvable affiche un message neutre', () => {
    cy.loginAs('theo@test.local', '/users/999999')

    cy.getCy('profile-not-found').should('contain.text', 'Ce profil n\'est pas disponible.')
  })
})

describe('Blocage', () => {
  it('Camille bloque Marc, qui disparaît, puis le débloque', () => {
    cy.loginAs('camille@test.local')
    searchAndOpen('marc', 'marc')
    cy.getCy('block-open').click()
    cy.getCy('block-confirm').click()

    cy.location('pathname').should('eq', '/friends')
    // Marc n'est plus abonné et n'apparaît plus dans la recherche
    cy.getCy('friends-tab-relations').click()
    texts('relation-name').should('not.include', 'marc')
    cy.getCy('friends-tab-search').click()
    cy.getCy('friends-search').type('marc')
    cy.getCy('search-empty').should('be.visible')

    // Comptes bloqués, puis déblocage
    cy.getCy('nav-profile').click()
    cy.getCy('profile-settings').click()
    cy.getCy('settings-blocked').click()
    texts('blocked-name').should('deep.equal', ['marc'])
    cy.getCy('unblock').click()
    cy.getCy('unblock-confirm').click()
    cy.getCy('blocked-empty').should('be.visible')

    // Visible de nouveau, mais l'abonnement n'est pas rétabli
    searchAndOpen('marc', 'marc')
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-relations').click()
    texts('relation-name').should('not.include', 'marc')
  })

  it('le compte bloqué ne trouve plus Camille', () => {
    cy.loginAs('camille@test.local')
    searchAndOpen('marc', 'marc')
    cy.getCy('block-open').click()
    cy.getCy('block-confirm').click()
    cy.location('pathname').should('eq', '/friends')

    cy.loginAs('marc@test.local')
    cy.getCy('nav-friends').click()
    cy.getCy('friends-tab-search').click()
    cy.getCy('friends-search').type('cam')
    cy.getCy('search-empty').should('be.visible')
  })

  it('un parent ne peut pas bloquer son compte enfant', () => {
    cy.loginAs('camille@test.local')
    searchAndOpen('sach', 'sacha')
    cy.getCy('block-open').click()
    cy.getCy('block-confirm').click()

    cy.getCy('block-error').should('have.text', 'Tu ne peux pas bloquer ton compte parent ou ton compte enfant.')
    cy.location('pathname').should('match', /^\/users\/\d+$/)
  })
})
