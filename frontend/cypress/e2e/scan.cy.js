// Scanner : caméra (composable useCamera, partagé avec « Poster un look ») puis fiche pré-remplie.
// La réponse de l'IA est simulée : aucun appel à OpenRouter pendant les tests.
const PHOTO = '../backend/public/uploads/demo/bob-sweat-chino.jpg'
const SCAN_RESULT = {
  name: 'Sweat gris',
  color: 'Gris chiné',
  type: 'Sweat',
  description: 'Molleton, col rond',
  categoryType: 'clothing',
  model: 'modele-de-test',
}

describe('Scanner', () => {
  beforeEach(() => {
    cy.intercept('POST', '/api/scan', (req) => {
      expect(req.body.image).to.match(/^data:image\/jpeg;base64,/)
      req.reply(SCAN_RESULT)
    }).as('scan')
    cy.loginAs('camille@test.local', '/scan')
  })

  it('prend la photo avec la caméra et pré-remplit la fiche', () => {
    cy.get('button[aria-label="Prendre la photo"]').should('not.be.disabled').click()

    cy.wait('@scan')
    cy.contains('.chip', 'Trouvé').should('be.visible')
    cy.get('input[placeholder="Pull bleu marine"]').should('have.value', 'Sweat gris')
    cy.contains('Analysé par modele-de-test')
  })

  it('analyse une photo importée', () => {
    cy.get('input[type="file"]').selectFile(PHOTO, { force: true })

    cy.wait('@scan')
    cy.get('input[placeholder="Pull bleu marine"]').should('have.value', 'Sweat gris')
  })

  it('coupe la caméra en quittant le scanner', () => {
    cy.get('button[aria-label="Prendre la photo"]').should('not.be.disabled')
    cy.get('video').then(([video]) => {
      const tracks = video.srcObject.getTracks()
      cy.get('a[aria-label="Fermer le scanner"]').click()
      cy.location('pathname').should('eq', '/')
      cy.wrap(tracks).each((track) => expect(track.readyState).to.eq('ended'))
    })
  })
})
