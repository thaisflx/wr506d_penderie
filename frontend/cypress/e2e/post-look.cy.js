// Poster un look du jour et reprendre un brouillon (docs/decisions.md, lot 2).
// Après chaque test, les looks de Camille dont la photo a été envoyée pendant le test (uploads/ootds) sont
// supprimés par l'API : leur photo disparaît avec eux (OotdPhotoCleaner), rien ne reste dans
// backend/public/uploads/ootds. Les fixtures utilisent uploads/demo et ne sont pas concernées.
const PHOTO = '../backend/public/uploads/demo/alice-mariniere.jpg'

// Appel direct à l'API de test avec le jeton de la session en cours
function apiRequest(method, url) {
  return cy.window().its('localStorage.penderie_token').then((token) =>
    cy.request({ method, url, headers: { Authorization: `Bearer ${token}`, 'X-Penderie-Env': 'test', Accept: 'application/ld+json' } }),
  )
}

function deleteUploadedLooks() {
  apiRequest('GET', '/api/me/ootds').then(({ body }) => {
    body.member
      .filter((ootd) => ootd.photo?.startsWith('/uploads/ootds/'))
      .forEach((ootd) => apiRequest('DELETE', `/api/ootds/${ootd.id}`))
  })
}

afterEach(deleteUploadedLooks)

const pickNames = () => cy.getCy('pick-item').then((els) => [...els].map((el) => el.textContent.trim()))

describe('Poster un look du jour', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
    cy.getCy('feed-post').click()
    cy.getCy('post-look').should('be.visible')
  })

  it('ne propose que les objets partageables', () => {
    // Pluriel français : 0 au singulier
    cy.getCy('post-look-count').should('have.text', '0 choisi')
    pickNames().should('include', 'Ponceuse').and('include', 'T-shirt blanc')
      .and('not.include', 'Carnet intime')
      .and('not.include', 'Contrat de location')
      .and('not.include', 'Bague de grand-mère')
      .and('not.include', 'Manteau en laine')
  })

  it('publie un look avec une photo de la galerie et deux objets', () => {
    cy.getCy('post-look-file').selectFile(PHOTO, { force: true })
    cy.getCy('post-look-photo').should('be.visible')
    cy.getCy('post-look-caption').type('Prête pour bricoler')
    cy.getCy('post-look-search').type('pon')
    pickNames().should('deep.equal', ['Ponceuse'])
    cy.contains('[data-cy="pick-item"]', 'Ponceuse').click().should('have.attr', 'aria-pressed', 'true')
    cy.getCy('post-look-search').clear().type('t-shirt')
    cy.contains('[data-cy="pick-item"]', 'T-shirt blanc').click()
    cy.getCy('post-look-count').should('have.text', '2 choisis')

    cy.getCy('post-look-publish').click()

    cy.location('pathname').should('eq', '/')
    cy.getCy('ootd-caption').first().should('have.text', 'Prête pour bricoler')
    cy.getCy('ootd-card').first().find('[data-cy="worn-item"]').should('have.length', 2)
    cy.getCy('ootd-card').first().find('[data-cy="ootd-when"]').should('have.text', 'à l\'instant')
  })

  it('date le look d\'aujourd\'hui et ne propose pas de date future', () => {
    const today = new Date().toLocaleDateString('sv-SE')
    cy.getCy('post-look-date').should('have.value', today).and('have.attr', 'max', today)
    cy.contains('.post-look-date', 'Aujourd\'hui')
  })

  it('demande une photo avant de publier, sans rien envoyer', () => {
    // Le back renvoie le même message : on vérifie que le front bloque avant tout envoi
    cy.intercept('POST', '/api/ootds', cy.spy().as('createOotd'))
    cy.getCy('post-look-caption').type('Sans photo')

    cy.getCy('post-look-publish').click()

    cy.getCy('post-look-error').should('have.text', 'Ajoute une photo de ta tenue avant de publier.')
    cy.location('pathname').should('eq', '/looks/nouveau')
    cy.get('@createOotd').should('not.have.been.called')
  })

  it('ne crée qu\'un seul look si la publication échoue puis est retentée', () => {
    cy.intercept('POST', '/api/ootds').as('createOotd')
    cy.intercept('POST', '/api/ootds/*/photo', { statusCode: 500, body: { detail: 'Erreur simulée' } }).as('photoKo')
    cy.getCy('post-look-file').selectFile(PHOTO, { force: true })
    cy.getCy('post-look-caption').type('Deux essais')

    cy.getCy('post-look-publish').click()
    cy.wait('@photoKo')
    cy.getCy('post-look-error').should('be.visible')
    cy.getCy('post-look-publish').click()
    cy.wait('@photoKo')

    cy.get('@createOotd.all').should('have.length', 1)
  })

  it('prend la photo avec la caméra', () => {
    cy.getCy('post-look-camera').click()
    cy.getCy('camera').should('be.visible')
    cy.getCy('camera-shutter').should('not.be.disabled').click()

    cy.getCy('camera').should('not.exist')
    cy.getCy('post-look-photo').should('have.attr', 'src').and('match', /^data:image\/jpeg;base64,/)
  })

  it('enregistre un brouillon sans photo', () => {
    cy.getCy('post-look-caption').type('Brouillon du test')
    cy.contains('[data-cy="pick-item"]', 'Jean brut').click()

    cy.getCy('post-look-draft').click()

    cy.location('pathname').should('eq', '/profile')
    apiRequest('GET', '/api/me/ootds?published=false').its('body.member').then((drafts) => {
      const draft = drafts.find((ootd) => ootd.caption === 'Brouillon du test')
      expect(draft.wornItems.map((item) => item.name)).to.deep.equal(['Jean brut'])
    })
  })
})

describe('Reprendre un brouillon', () => {
  beforeEach(() => {
    cy.loginAs('camille@test.local')
    apiRequest('GET', '/api/me/ootds?published=false').then(({ body }) => {
      cy.visit(`/looks/${body.member[0].id}/modifier`)
    })
  })

  it('reprend le brouillon et le publie avec une photo', () => {
    cy.getCy('post-look-caption').should('have.value', 'Idée pour la soirée')
    cy.getCy('post-look-count').should('have.text', '1 choisi')
    cy.contains('[data-cy="pick-item"]', 'Baskets montantes').should('have.attr', 'aria-pressed', 'true')

    cy.getCy('post-look-file').selectFile(PHOTO, { force: true })
    cy.getCy('post-look-publish').click()

    cy.location('pathname').should('eq', '/')
    cy.getCy('ootd-caption').first().should('have.text', 'Idée pour la soirée')
  })

  it('supprime le brouillon', () => {
    cy.getCy('post-look-delete').click()

    cy.location('pathname').should('eq', '/profile')
    apiRequest('GET', '/api/me/ootds?published=false').its('body.member').should('have.length', 0)
  })
})
