// url=https://www.figma.com/design/raAjSURmLHQhX6xeh5IEip/DATING-WEB--Copy-?node-id=197-1225
// source=src/messages.php
// component=StandardChatInput

import figma from 'figma'
const instance = figma.selectedInstance

const mode = instance.getEnum('Property 1', {
  'Default': 'default',
  'Variant2': 'variant2'
})

const placeholder = mode === 'default' ? 'Type a message...' : 'What time do you get up today ?'

export default {
  id: 'standard-chat-input',
  example: figma.code`
<div class="chat-input-wrapper">
    <div class="chat-input-box">
        <input type="text" id="msgInput" placeholder="${placeholder}" onkeypress="handleKeyPress(event)">
        <button class="btn-send" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>
  `,
  metadata: {
    nestable: true
  }
}
