// url=https://www.figma.com/design/raAjSURmLHQhX6xeh5IEip/DATING-WEB--Copy-?node-id=245-473
// source=src/login.html
// component=EmailInput

import figma from 'figma'
const instance = figma.selectedInstance

const mode = instance.getEnum('Property 1', {
  'Default': 'default',
  'Variant2': 'variant2'
})

const placeholder = mode === 'default' ? 'your@email.com' : 'hachi@gmail.com'

export default {
  id: 'email-input',
  example: figma.code`
<input type="email" id="email" name="email" placeholder="${placeholder}" required>
  `,
  metadata: {
    nestable: true
  }
}
