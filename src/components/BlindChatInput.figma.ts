// url=https://www.figma.com/design/raAjSURmLHQhX6xeh5IEip/DATING-WEB--Copy-?node-id=185-1375
// source=src/messages.php
// component=BlindChatInput

import figma from 'figma'
const instance = figma.selectedInstance

const mode = instance.getEnum('Property 1', {
  'Default': 'default',
  'Variant2': 'variant2'
})

const placeholder = mode === 'default' ? "Whisper your soul's truth..." : 'ahihihihihi'

export default {
  id: 'blind-chat-input',
  example: figma.code`
<div class="chat-input-wrapper">
    <div class="ai-prompts">
        <span onclick="insertPrompt('Ask about hobbies')"><i class="fa-solid fa-wand-magic-sparkles"></i> Ask about hobbies</span>
    </div>
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
