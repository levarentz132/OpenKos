{
  "name": "HighlanderStay - WhatsApp Bot & Chatwoot Handover (OpenKos Live API)",
  "nodes": [
    {
      "parameters": {
        "httpMethod": "POST",
        "path": "chatwoot-wa-bot",
        "options": {}
      },
      "type": "n8n-nodes-base.webhook",
      "typeVersion": 2.1,
      "position": [
        -80,
        96
      ],
      "id": "985d2e12-49ed-4ff3-9e61-8cb2e6d03e65",
      "name": "Webhook",
      "webhookId": "73494750-0dda-46d1-8d0a-71678bf9b88b"
    },
    {
      "parameters": {
        "jsCode": "const body = $json.body || $json;\n\n// Basic Data Extraction\nconst content = body.content || body.message?.content || '';\nconst messageType = body.message_type || body.message?.message_type || '';\n\nconst waMessage =\n  body.entry?.[0]?.changes?.[0]?.value?.messages?.[0] ||\n  body.messages?.[0] ||\n  null;\n\nlet interactiveId =\n  waMessage?.interactive?.list_reply?.id ||\n  waMessage?.interactive?.button_reply?.id ||\n  '';\n\ninteractiveId = String(interactiveId || '').replace(/^=/, '').trim();\n\nconst interactiveTitle =\n  waMessage?.interactive?.list_reply?.title ||\n  waMessage?.interactive?.button_reply?.title ||\n  '';\n\nconst fromPhone = waMessage?.from || '';\nconst conversationId = body.conversation?.id || body.conversation_id || body.message?.conversation_id;\nconst accountId = body.account?.id || body.account_id || 1;\nconst sender = body.conversation?.meta?.sender || body.sender || body.contact || {};\nconst contactId = body.sender?.id || body.conversation?.contact_inbox?.contact_id || body.conversation?.meta?.sender?.id || body.contact?.id || null;\n\nlet phone = fromPhone || sender.phone_number || sender.identifier || sender.source_id || body.conversation?.contact_inbox?.source_id || '';\nphone = String(phone).replace(/\\D/g, '');\nif (phone.startsWith('0')) phone = '62' + phone.slice(1);\n\nconst rawText = String(content || waMessage?.text?.body || '').replace(/^=/, '').trim();\nconst text = rawText.toLowerCase();\n\nconst conversationLabels = body.conversation?.labels || [];\nconst hasWaitingAdminLabel = conversationLabels.includes('waiting_admin');\n\n// State Data\nconst staticData = $getWorkflowStaticData('global');\nconst stateKey = `state_${conversationId || phone}`;\nconst state = staticData[stateKey] || {};\n\n// Map existing survey label from Chatwoot to Location ID if available\nconst existingSurveyLabel = conversationLabels.find(l => l.startsWith('survey_') && l !== 'survey_general');\nlet fallbackLocationIdFromLabel = null;\nif (existingSurveyLabel) {\n  const mapLabelToId = {\n    'survey_alpukat': 'LOC_ALPUKAT',\n    'survey_gpv': 'LOC_APARTEMEN_SEDAYU',\n    'survey_green_garden': 'LOC_GREEN_GARDEN',\n    'survey_greenville': 'LOC_GREENVILLE',\n    'survey_jelambar': 'LOC_JELAMBAR',\n    'survey_pakjo': 'LOC_PAKJO',\n    'survey_pedongkelan': 'LOC_PEDONGKELAN',\n    'survey_pesing_baru': 'LOC_PESING_BARU',\n    'survey_pesing_lama': 'LOC_PESING_LAMA',\n    'survey_rajawali': 'LOC_RAJAWALI',\n    'survey_sumur_bor': 'LOC_SUMUR_BOR',\n    'survey_taman_mahkota': 'LOC_TAMAN_MAHKOTA',\n    'survey_td_guest': 'LOC_TD_GUEST',\n    'survey_td647': 'LOC_TD647',\n    'survey_td795': 'LOC_TD795'\n  };\n  fallbackLocationIdFromLabel = mapLabelToId[existingSurveyLabel] || `LOC_${existingSurveyLabel.replace(/^survey_/, '').toUpperCase()}`;\n}\n\nlet lastLocationId = state.lastLocationId || fallbackLocationIdFromLabel || null;\n\nlet action = 'send_start_buttons';\nlet selectedArea = null;\nlet selectedLocationId = interactiveId || null;\nlet selectedLocationTitle = interactiveTitle || rawText || null;\nlet locationLabel = null;\n\n// Admin Reset Command\nconst adminResetCommands = ['#boton', '/bot', 'bot on', 'aktifkan bot'];\nif (messageType === 'outgoing' && adminResetCommands.includes(text)) {\n  delete staticData[stateKey];\n  return [{\n    json: {\n      skip: false,\n      action: 'bot_reactivated',\n      reason: 'Admin reactivated bot',\n      phone, contactId, text, rawText, accountId, conversationId, labels: conversationLabels, hasWaitingAdminLabel\n    }\n  }];\n}\n\n// Skip outgoing Chatwoot messages\nif (messageType && messageType !== 'incoming') {\n  return [{\n    json: {\n      skip: true, action: 'skip', reason: 'Not incoming message', phone, contactId, text, rawText, accountId, conversationId, labels: conversationLabels, hasWaitingAdminLabel\n    }\n  }];\n}\n\n// Waiting Admin Handling\nconst waitingAdminReactivateMap = {\n  'menu': 'send_start_buttons', '/menu': 'send_start_buttons', 'bot': 'send_start_buttons', 'mulai': 'send_start_buttons', 'start': 'send_start_buttons', 'halo': 'send_start_buttons',\n  'katalog': 'send_catalog_area_list', '/katalog': 'send_catalog_area_list', '1': 'send_catalog_area_list',\n  'aturan': 'send_rules', '/aturan': 'send_rules', '2': 'send_rules'\n};\n\nconst adminRequestCommands = ['admin', 'bantuan', 'bantuan admin', '/bantuan', '3'];\nconst waitingAdminReactivateInteractiveIds = ['MENU_KATALOG', 'MENU_ATURAN', 'MENU_START', 'BTN_MENU', 'MENU'];\nconst waitingAdminAskAdminInteractiveIds = ['ADMIN_HELP', 'ASK_ADMIN', 'BTN_ADMIN', 'TALK_ADMIN'];\n\nif (hasWaitingAdminLabel) {\n  const reactivatedAction = waitingAdminReactivateMap[text] || null;\n  const isReactivateByButton = waitingAdminReactivateInteractiveIds.includes(interactiveId);\n  const isAskAdminAgain = adminRequestCommands.includes(text) || waitingAdminAskAdminInteractiveIds.includes(interactiveId);\n\n  if (isAskAdminAgain) {\n    return [{\n      json: {\n        skip: false, action: 'already_waiting_admin_reply', reason: 'User requested admin again while already waiting_admin',\n        phone, contactId, text, rawText, content, accountId, conversationId, interactiveId, labels: conversationLabels, hasWaitingAdminLabel,\n        shouldRemoveWaitingAdmin: false, replyText: 'Kak, sudah saya teruskan ke admin ya. Mohon tunggu sebentar 🙏'\n      }\n    }];\n  }\n\n  if (reactivatedAction || isReactivateByButton) {\n    let nextAction = reactivatedAction || 'send_start_buttons';\n    if (interactiveId === 'MENU_KATALOG') nextAction = 'send_catalog_area_list';\n    if (interactiveId === 'MENU_ATURAN') nextAction = 'send_rules';\n    return [{\n      json: {\n        skip: false, action: nextAction, reason: 'User reactivated bot while waiting_admin',\n        phone, contactId, text, rawText, content, accountId, conversationId, interactiveId, labels: conversationLabels, hasWaitingAdminLabel, shouldRemoveWaitingAdmin: true\n      }\n    }];\n  }\n\n  return [{\n    json: {\n      skip: true, action: 'skip', reason: 'Conversation has waiting_admin label',\n      phone, contactId, text, rawText, content, accountId, conversationId, interactiveId, labels: conversationLabels, hasWaitingAdminLabel, shouldRemoveWaitingAdmin: false\n    }\n  }];\n}\n\nfunction makeSurveyLocationLabel(locationId) {\n  const map = {\n    LOC_GREENVILLE: 'survey_greenville',\n    LOC_TD795: 'survey_td795',\n    LOC_TD647: 'survey_td647',\n    LOC_ALPUKAT: 'survey_alpukat',\n    LOC_TD_GUEST: 'survey_td_guest',\n    LOC_PESING_LAMA: 'survey_pesing_lama',\n    LOC_PESING_BARU: 'survey_pesing_baru',\n    LOC_GREEN_GARDEN: 'survey_green_garden',\n    LOC_SUMUR_BOR: 'survey_sumur_bor',\n    LOC_PEDONGKELAN: 'survey_pedongkelan',\n    LOC_TAMAN_MAHKOTA: 'survey_taman_mahkota',\n    LOC_JELAMBAR: 'survey_jelambar',\n    LOC_RAJAWALI: 'survey_rajawali',\n    LOC_PAKJO: 'survey_pakjo',\n    LOC_APARTEMEN_SEDAYU: 'survey_gpv',\n    LOC_APARTEMEN: 'survey_gpv',\n    LOC_SEDAYU: 'survey_gpv'\n  };\n  return map[locationId] || `survey_${String(locationId).replace(/^LOC_/, '').toLowerCase()}`;\n}\n\nconst textMap = {\n  // Main Menu\n  'katalog': 'MENU_KATALOG', '/katalog': 'MENU_KATALOG', '1': 'MENU_KATALOG',\n  'aturan': 'MENU_ATURAN', '/aturan': 'MENU_ATURAN', '2': 'MENU_ATURAN',\n  'admin': 'ADMIN_HELP', 'bantuan': 'ADMIN_HELP', '3': 'ADMIN_HELP',\n  // Area List\n  'cengkareng': 'AREA_CENGKARENG', 'tangerang': 'AREA_TANGERANG', 'kebon jeruk': 'AREA_KEBON_JERUK', 'kebonjeruk': 'AREA_KEBON_JERUK',\n  'grogol': 'AREA_GROGOL', 'palembang': 'AREA_PALEMBANG', 'kemayoran': 'AREA_KEMAYORAN', 'bogor': 'AREA_BOGOR', 'curug nangka': 'AREA_BOGOR',\n  // Property Titles & Aliases\n  'alpukat': 'LOC_ALPUKAT',\n  'apartemen': 'LOC_APARTEMEN_SEDAYU', 'apartemen sedayu': 'LOC_APARTEMEN_SEDAYU', 'sedayu': 'LOC_APARTEMEN_SEDAYU', 'gpv': 'LOC_APARTEMEN_SEDAYU', 'green park view': 'LOC_APARTEMEN_SEDAYU',\n  'greenville': 'LOC_GREENVILLE', 'greenville mangga': 'LOC_GREENVILLE', 'mangga': 'LOC_GREENVILLE',\n  'green garden': 'LOC_GREEN_GARDEN', 'greengarden': 'LOC_GREEN_GARDEN',\n  'jelambar': 'LOC_JELAMBAR',\n  'pakjo': 'LOC_PAKJO', 'pak jo': 'LOC_PAKJO',\n  'pedongkelan': 'LOC_PEDONGKELAN',\n  'pesing baru': 'LOC_PESING_BARU', 'pesingbaru': 'LOC_PESING_BARU',\n  'pesing lama': 'LOC_PESING_LAMA', 'pesinglama': 'LOC_PESING_LAMA', 'koneng': 'LOC_PESING_LAMA',\n  'rajawali': 'LOC_RAJAWALI',\n  'sumur bor': 'LOC_SUMUR_BOR', 'sumurbor': 'LOC_SUMUR_BOR',\n  'taman mahkota': 'LOC_TAMAN_MAHKOTA', 'mahkota': 'LOC_TAMAN_MAHKOTA',\n  'tanjung duren guest': 'LOC_TD_GUEST', 'td guest': 'LOC_TD_GUEST', 'guest': 'LOC_TD_GUEST',\n  'tanjung duren 647': 'LOC_TD647', 'td647': 'LOC_TD647', 'td 647': 'LOC_TD647', '647': 'LOC_TD647',\n  'tanjung duren 795': 'LOC_TD795', 'td795': 'LOC_TD795', 'td 795': 'LOC_TD795', '795': 'LOC_TD795'\n};\n\nif (!interactiveId && rawText.startsWith('MENU_')) interactiveId = rawText;\nif (!interactiveId && rawText.startsWith('AREA_')) interactiveId = rawText;\nif (!interactiveId && rawText.startsWith('LOC_')) { interactiveId = rawText; selectedLocationId = rawText; }\nif (!interactiveId && rawText.startsWith('PAY_')) interactiveId = rawText;\nif (!interactiveId && rawText.startsWith('KEEPER_')) interactiveId = rawText;\nif (!interactiveId && textMap[text]) {\n  interactiveId = textMap[text];\n  if (interactiveId.startsWith('LOC_')) selectedLocationId = interactiveId;\n}\n\nif (interactiveId === 'MENU_KATALOG') action = 'send_catalog_area_list';\nelse if (interactiveId === 'MENU_ATURAN') action = 'send_rules';\nelse if (interactiveId === 'ADMIN_HELP') action = 'handover_admin';\nelse if (interactiveId === 'AREA_CENGKARENG') { action = 'get_area_locations'; selectedArea = 'Cengkareng'; }\nelse if (interactiveId === 'AREA_TANGERANG') { action = 'get_area_locations'; selectedArea = 'Tangerang'; }\nelse if (interactiveId === 'AREA_KEBON_JERUK') { action = 'get_area_locations'; selectedArea = 'Kebon Jeruk'; }\nelse if (interactiveId === 'AREA_GROGOL') { action = 'get_area_locations'; selectedArea = 'Grogol'; }\nelse if (interactiveId === 'AREA_PALEMBANG') { action = 'get_area_locations'; selectedArea = 'Palembang'; }\nelse if (interactiveId === 'AREA_KEMAYORAN') { action = 'get_area_locations'; selectedArea = 'Kemayoran'; }\nelse if (interactiveId === 'AREA_BOGOR') { action = 'get_area_locations'; selectedArea = 'Bogor'; }\nelse if (interactiveId.startsWith('PAY_')) {\n  action = 'payment_selected_location';\n  selectedLocationId = interactiveId.replace('PAY_', '') || lastLocationId;\n  selectedLocationTitle = rawText || selectedLocationId;\n  locationLabel = makeSurveyLocationLabel(selectedLocationId);\n}\nelse if (interactiveId.startsWith('KEEPER_')) {\n  action = 'contact_keeper';\n  selectedLocationId = interactiveId.replace('KEEPER_', '') || lastLocationId;\n  selectedLocationTitle = rawText || selectedLocationId;\n  locationLabel = makeSurveyLocationLabel(selectedLocationId);\n}\nelse if (interactiveId.startsWith('LOC_')) {\n  action = 'get_property_info';\n  selectedLocationId = interactiveId;\n  locationLabel = makeSurveyLocationLabel(selectedLocationId);\n}\nelse if (text.includes('bayar') || text.includes('pembayaran') || text.includes('rekening') || text.includes('transfer')) {\n  action = 'payment_selected_location';\n  selectedLocationId = selectedLocationId || lastLocationId;\n  locationLabel = makeSurveyLocationLabel(selectedLocationId);\n}\nelse if (text.includes('penjaga') || text.includes('kontak') || text.includes('hubungi penjaga')) {\n  action = 'contact_keeper';\n  selectedLocationId = selectedLocationId || lastLocationId;\n  locationLabel = makeSurveyLocationLabel(selectedLocationId);\n}\nelse if (text.includes('katalog')) action = 'send_catalog_area_list';\nelse if (text.includes('aturan')) action = 'send_rules';\nelse if (text.includes('admin') || text.includes('bantuan')) action = 'handover_admin';\nelse action = 'send_start_buttons';\n\nif (selectedLocationId && selectedLocationId.startsWith('LOC_')) {\n  staticData[stateKey] = {\n    ...state,\n    lastLocationId: selectedLocationId\n  };\n}\n\nreturn [{\n  json: {\n    skip: false, action, phone, contactId, text, rawText, content, accountId, conversationId, selectedArea, selectedLocationId, selectedLocationTitle, locationLabel, interactiveId, labels: conversationLabels, hasWaitingAdminLabel\n  }\n}];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        128,
        80
      ],
      "id": "ffa41d9e-f80b-40ff-997d-b900713d7138",
      "name": "Detect Action"
    },
    {
      "parameters": {
        "rules": {
          "values": [
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "5e8563d8-25a5-4da1-bbf1-29fa97c4082f",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "send_start_buttons",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "start"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "41272a9b-4ee3-4ef4-a7a8-45a62ba24c1e",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "send_catalog_area_list",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "catalog area list"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "58e24c23-1447-4a24-9bd5-77fe99d5f7fb",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "send_rules",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "rules"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "1eb7c9f0-4cc7-4142-99b2-d0210ada174b",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "get_area_locations",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "area location"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "fbfe4bad-e0c4-482c-908d-8a7f086847b3",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "get_property_info",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "property info"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "da296705-12fc-4b62-8aaa-e95b7689dc8b",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "handover_admin",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "admin"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "4183f406-e3ee-4a2a-8c64-40b34b32a1c7",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "payment_selected_location",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "payment"
            },
            {
              "conditions": {
                "options": {
                  "caseSensitive": true,
                  "leftValue": "",
                  "typeValidation": "strict",
                  "version": 3
                },
                "conditions": [
                  {
                    "id": "2d3094d2-608b-40d8-8523-10a3a5bef1c7",
                    "leftValue": "={{ $json.action }}",
                    "rightValue": "contact_keeper",
                    "operator": {
                      "type": "string",
                      "operation": "equals"
                    }
                  }
                ],
                "combinator": "and"
              },
              "renameOutput": true,
              "outputKey": "contact keeper"
            }
          ]
        },
        "options": {}
      },
      "type": "n8n-nodes-base.switch",
      "typeVersion": 3.4,
      "position": [
        288,
        -16
      ],
      "id": "5ae8224e-1300-4cb6-b96f-ad474aad2356",
      "name": "Switch"
    },
    {
      "parameters": {
        "conditions": {
          "options": {
            "caseSensitive": true,
            "leftValue": "",
            "typeValidation": "loose",
            "version": 3
          },
          "conditions": [
            {
              "id": "4a7e2487-101c-4d69-89f1-6b5c5b211c64",
              "leftValue": "={{ Boolean($json.hasWaitingAdminLabel) }}",
              "rightValue": true,
              "operator": {
                "type": "boolean",
                "operation": "true",
                "singleValue": true
              }
            }
          ],
          "combinator": "and"
        },
        "options": {}
      },
      "type": "n8n-nodes-base.if",
      "typeVersion": 2.3,
      "position": [
        448,
        -480
      ],
      "id": "eae7bf94-7873-4618-9205-584006653b79",
      "name": "If"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Detect Action').item.json.conversationId }}/labels",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"labels\": {{ JSON.stringify(($json.labels || []).filter(label => label !== 'waiting_admin')) }}\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        720,
        -560
      ],
      "id": "71cbcaea-4e60-4238-b7fe-4bcf4b0376b1",
      "name": "HTTP Request10"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"to\": \"={{ $('Switch').item.json.phone }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"body\": {\n      \"text\": \"Halo 👋 Selamat datang di HighlanderStay.\\n\\nSilakan pilih menu:\"\n    },\n    \"action\": {\n      \"buttons\": [\n        { \"type\": \"reply\", \"reply\": { \"id\": \"MENU_KATALOG\", \"title\": \"Katalog\" } },\n        { \"type\": \"reply\", \"reply\": { \"id\": \"MENU_ATURAN\", \"title\": \"Aturan\" } },\n        { \"type\": \"reply\", \"reply\": { \"id\": \"ADMIN_HELP\", \"title\": \"Admin\" } }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        896,
        -560
      ],
      "id": "3154da7e-61e3-4d6d-8ff7-1a403847aa1f",
      "name": "HTTP Request14",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"to\": \"={{ $json.phone }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"body\": {\n      \"text\": \"Halo 👋 Selamat datang di HighlanderStay.\\n\\nSilakan pilih menu:\\n\\n⚠️ *INFORMASI PEMBAYARAN*\\nPembayaran hanya dilakukan ke rekening resmi atas nama *Lorentz Levanone* (BCA: 1980023539).\\nJika ada pihak yang meminta pembayaran ke rekening lain, jangan melakukan transfer dan segera hubungi admin HighlanderStay.\"\n    },\n    \"action\": {\n      \"buttons\": [\n        { \"type\": \"reply\", \"reply\": { \"id\": \"MENU_KATALOG\", \"title\": \"Katalog\" } },\n        { \"type\": \"reply\", \"reply\": { \"id\": \"MENU_ATURAN\", \"title\": \"Aturan\" } },\n        { \"type\": \"reply\", \"reply\": { \"id\": \"ADMIN_HELP\", \"title\": \"Admin\" } }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        704,
        -400
      ],
      "id": "18cf7887-f83a-44aa-9f0a-74be348f93da",
      "name": "HTTP Request",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"to\": \"={{$json.phone}}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"list\",\n    \"header\": { \"type\": \"text\", \"text\": \"Katalog HighlanderStay\" },\n    \"body\": { \"text\": \"Silakan pilih area yang kakak minati:\" },\n    \"footer\": { \"text\": \"HighlanderStay\" },\n    \"action\": {\n      \"button\": \"Pilih Area\",\n      \"sections\": [\n        {\n          \"title\": \"Area\",\n          \"rows\": [\n            { \"id\": \"AREA_CENGKARENG\", \"title\": \"Cengkareng\", \"description\": \"Sumur Bor, Pedongkelan\" },\n            { \"id\": \"AREA_TANGERANG\", \"title\": \"Tangerang\", \"description\": \"Taman Mahkota / Bandara\" },\n            { \"id\": \"AREA_KEBON_JERUK\", \"title\": \"Kebon Jeruk\", \"description\": \"Green Garden, Greenville, Pesing\" },\n            { \"id\": \"AREA_GROGOL\", \"title\": \"Grogol\", \"description\": \"Tanjung Duren, Alpukat, Jelambar\" },\n            { \"id\": \"AREA_PALEMBANG\", \"title\": \"Palembang\", \"description\": \"Pakjo\" },\n            { \"id\": \"AREA_KEMAYORAN\", \"title\": \"Kemayoran\", \"description\": \"Rajawali\" },\n            { \"id\": \"AREA_BOGOR\", \"title\": \"Bogor\", \"description\": \"Curug Nangka\" }\n          ]\n        }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        624,
        -256
      ],
      "id": "6d1bcf5d-752e-4b68-b7fb-55f75baefeb8",
      "name": "HTTP Request1",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Detect Action').item.json.conversationId }}/labels",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"labels\": {{ JSON.stringify(($json.labels || []).filter(label => label !== 'waiting_admin')) }}\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        880,
        -256
      ],
      "id": "9ddbc447-c183-4908-a898-b6f24e21e4ae",
      "name": "HTTP Request17"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"to\": \"={{$json.phone}}\",\n  \"type\": \"text\",\n  \"text\": {\n    \"preview_url\": false,\n    \"body\": \"📋 *Aturan HighlanderStay*\\n\\n1. Deposit Rp500.000, khusus beberapa unit tertentu bisa berbeda. Pembayaran wajib atas Nama Lorentz Levanone BCA 1980023539 (diluar itu bukan rekening kami dan kami berhak memberhentikan kontrak sewa).\\n2. Maksimal 2 orang per kamar.\\n3. Jika berdua, ada tambahan biaya sesuai ketentuan lokasi.\\n4. Tidak boleh membawa hewan peliharaan.\\n5. Jam tamu maksimal pukul 23.00.\\n6. Listrik menggunakan token PLN.\\n7. Air mengikuti ketentuan masing-masing lokasi.\\n8. Kamar wajib dijaga kebersihan dan ketertibannya.\\n\\nUntuk tanya detail aturan lokasi tertentu, klik Admin.\"\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        640,
        -128
      ],
      "id": "73b8a9c9-4b6f-4a84-8787-2323bebb0bd4",
      "name": "HTTP Request8",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Detect Action').item.json.conversationId }}/labels",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"labels\": {{ JSON.stringify(($json.labels || []).filter(label => label !== 'waiting_admin')) }}\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        880,
        -128
      ],
      "id": "72af957f-269b-4862-b638-014762091624",
      "name": "HTTP Request18"
    },
    {
      "parameters": {
        "url": "https://dashboard.highlanderstay.com/api/v1/available-rooms",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        656,
        32
      ],
      "id": "bcb40a12-cac7-42c6-bdfc-a03663398e76",
      "name": "OpenKos: Fetch Available Rooms"
    },
    {
      "parameters": {
        "jsCode": "const original = $('Detect Action').first().json;\nconst phone = original.phone;\nconst selectedArea = String(original.selectedArea || '').toLowerCase().trim();\n\nconst response = $input.first().json;\nconst items = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : []);\n\nconst filteredRows = items.filter(prop => {\n  const propKec = String(prop.kecamatan || '').toLowerCase().trim();\n  const propName = String(prop.name || '').toLowerCase().trim();\n  const propSlug = String(prop.slug || '').toLowerCase().trim();\n\n  if (selectedArea === 'cengkareng') {\n    return propName.includes('cengkareng') || propName.includes('pedongkelan') || propName.includes('sumur bor') || propSlug.includes('cengkareng') || propSlug.includes('pedongkelan') || propSlug.includes('sumur-bor');\n  }\n\n  if (!propKec) return false;\n  return propKec === selectedArea || propKec.includes(selectedArea) || selectedArea.includes(propKec);\n});\n\nlet rows = filteredRows.slice(0, 10).map(prop => {\n  const availableRooms = Array.isArray(prop.available_rooms) ? prop.available_rooms.length : 0;\n  const stockText = prop.availability_status || (availableRooms > 0 ? `Ready ${availableRooms}` : 'Kamar full');\n  const rawSlug = prop.slug || prop.name || '';\n  const idCol = `LOC_${rawSlug.toUpperCase().replace(/[^A-Z0-9_]/g, '_')}`;\n\n  return {\n    id: idCol.slice(0, 200),\n    title: String(prop.name || 'Kost').slice(0, 24),\n    description: `${stockText} - ${prop.price_range || ''}`.slice(0, 72)\n  };\n});\n\nif (!rows.length) {\n  rows = [\n    {\n      id: 'NO_LOCATION_FOUND',\n      title: 'Belum tersedia',\n      description: `Belum ada lokasi aktif di area ${original.selectedArea}`.slice(0, 72)\n    }\n  ];\n}\n\nreturn [\n  {\n    json: {\n      phone,\n      selectedArea: original.selectedArea,\n      whatsappPayload: {\n        messaging_product: 'whatsapp',\n        to: phone,\n        type: 'interactive',\n        interactive: {\n          type: 'list',\n          header: {\n            type: 'text',\n            text: String(original.selectedArea).slice(0, 60)\n          },\n          body: {\n            text: `Silakan pilih lokasi HighlanderStay di area ${original.selectedArea}:`\n          },\n          footer: {\n            text: 'HighlanderStay'\n          },\n          action: {\n            button: 'Pilih Lokasi',\n            sections: [\n              {\n                title: String(original.selectedArea).slice(0, 24),\n                rows\n              }\n            ]\n          }\n        }\n      }\n    }\n  }\n];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        848,
        32
      ],
      "id": "e2c6e4c8-540e-4bb5-ab86-cda89b8bb8b1",
      "name": "Code in JavaScript1"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{$json.whatsappPayload}}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1040,
        32
      ],
      "id": "d0e12345-6789-4abc-def0-1234567890ab",
      "name": "HTTP Request9",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "url": "https://dashboard.highlanderstay.com/api/v1/available-rooms",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        656,
        176
      ],
      "id": "d2a095fc-1216-43d9-9528-7fb8ca6363cb",
      "name": "OpenKos: Fetch Property Info"
    },
    {
      "parameters": {
        "jsCode": "const original = $('Detect Action').first().json;\nconst phone = original.phone;\nconst selectedLocationId = String(original.selectedLocationId || '').trim();\n\nconst response = $input.first().json;\nconst items = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : []);\n\nconst row = items.find(prop => {\n  const rawSlug = String(prop.slug || '').toLowerCase();\n  const rawName = String(prop.name || '').toLowerCase();\n  const idCol = `LOC_${(prop.slug || prop.name || '').toUpperCase().replace(/[^A-Z0-9_]/g, '_')}`.toLowerCase();\n  const targetIdCol = selectedLocationId.toLowerCase();\n  const target = selectedLocationId.replace(/^LOC_/, '').toLowerCase();\n\n  if (targetIdCol && idCol === targetIdCol) return true;\n  if (target && (rawSlug === target || rawName === target)) return true;\n  if (target && target.length > 2 && (rawSlug.includes(target) || target.includes(rawSlug))) return true;\n  if (target && target.length > 2 && (rawName.includes(target) || target.includes(rawName))) return true;\n  return false;\n});\n\nif (!row) {\n  return [\n    {\n      json: {\n        phone,\n        selectedLocationId,\n        error: true,\n        whatsappPayload: {\n          messaging_product: 'whatsapp',\n          to: phone,\n          type: 'text',\n          text: {\n            preview_url: false,\n            body: `Maaf kak, data lokasi ${selectedLocationId} belum ditemukan. Silakan klik Katalog untuk pilih ulang.`\n          }\n        }\n      }\n    }\n  ];\n}\n\nfunction clean(value, fallback = '') {\n  return String(value ?? fallback).trim();\n}\n\nconst title = clean(row.name || 'HighlanderStay');\nconst area = clean(row.kecamatan, '-');\nconst price = clean(row.price_range, '-');\nconst availableRooms = Array.isArray(row.available_rooms) ? row.available_rooms.length : 0;\nconst stockText = clean(row.availability_status) || (availableRooms > 0 ? `Ready ${availableRooms} kamar` : 'Kamar full');\nconst imageUrl = clean(row.image_url);\nconst facilities = clean(row.description, '-');\nconst penjaga = clean(row.phone);\nconst linkAlamat = clean(row.address_url);\nconst availableRoomsStr = Array.isArray(row.available_rooms) ? row.available_rooms.join(', ') : '-';\n\nconst replyText =\n`🏠 *${title}*\n📍 Area: ${area}\n💰 Harga: ${price}\n🚪 Ketersediaan: ${stockText} (${availableRoomsStr})\n\nFasilitas:\n${facilities}\n\n📌 Link alamat:\n${linkAlamat || '-'}\n\n👤 Nomor penjaga:\n${penjaga || '-'}\n\nDeposit mengikuti ketentuan HighlanderStay.\nMaksimal 2 orang per kamar.\n\n⚠️ *INFORMASI PEMBAYARAN*\nPembayaran hanya dilakukan ke rekening resmi atas nama *Lorentz Levanone* (BCA: 1980023539).\nJika ada pihak yang meminta pembayaran ke rekening lain, mohon jangan melakukan transfer dan segera hubungi admin HighlanderStay`;\n\nfunction makeSurveyLocationLabel(locationId, slug, name) {\n  const map = {\n    LOC_GREENVILLE: 'survey_greenville',\n    LOC_TD795: 'survey_td795',\n    LOC_TD647: 'survey_td647',\n    LOC_ALPUKAT: 'survey_alpukat',\n    LOC_TD_GUEST: 'survey_td_guest',\n    LOC_PESING_LAMA: 'survey_pesing_lama',\n    LOC_PESING_BARU: 'survey_pesing_baru',\n    LOC_GREEN_GARDEN: 'survey_green_garden',\n    LOC_SUMUR_BOR: 'survey_sumur_bor',\n    LOC_PEDONGKELAN: 'survey_pedongkelan',\n    LOC_TAMAN_MAHKOTA: 'survey_taman_mahkota',\n    LOC_JELAMBAR: 'survey_jelambar',\n    LOC_RAJAWALI: 'survey_rajawali',\n    LOC_PAKJO: 'survey_pakjo',\n    LOC_APARTEMEN_SEDAYU: 'survey_gpv',\n    LOC_APARTEMEN: 'survey_gpv',\n    LOC_SEDAYU: 'survey_gpv'\n  };\n  const key = String(locationId || '').toUpperCase();\n  if (map[key]) return map[key];\n  const s = String(slug || name || '').toLowerCase().replace(/[^a-z0-9]/g, '_');\n  return `survey_${s}`;\n}\n\nconst idCol = selectedLocationId.startsWith('LOC_') ? selectedLocationId : `LOC_${(row.slug || row.name).toUpperCase().replace(/[^A-Z0-9_]/g, '_')}`;\nconst payButtonId = `PAY_${idCol}`;\nconst keeperButtonId = `KEEPER_${idCol}`;\nconst locationLabel = makeSurveyLocationLabel(idCol, row.slug, row.name);\nconst existingLabels = (original.labels || []).filter(l => !l.startsWith('survey_') && l !== 'waiting_admin');\nconst labelsToSend = [...new Set([...existingLabels, locationLabel])];\n\nreturn [\n  {\n    json: {\n      phone,\n      selectedLocationId: idCol,\n      selectedLocationTitle: title,\n      selectedArea: area,\n      hasImage: imageUrl.startsWith('http'),\n      property: {\n        id: idCol,\n        area,\n        locationName: title,\n        displayTitle: title,\n        price,\n        availableRooms,\n        availabilityText: stockText,\n        image: imageUrl,\n        facilities,\n        penjaga,\n        linkAlamat\n      },\n      replyText,\n      payButtonId,\n      keeperButtonId,\n      locationLabel,\n      labelsToSend\n    }\n  }\n];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        848,
        176
      ],
      "id": "e8d66df2-2c6e-4f11-93e1-0c46eecdc714",
      "name": "Code in JavaScript2"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"messaging_product\": \"whatsapp\",\n  \"to\": \"{{ $('Code in JavaScript2').item.json.phone }}\",\n  \"type\": \"interactive\",\n  \"interactive\": {\n    \"type\": \"button\",\n    \"header\": {\n      \"type\": \"image\",\n      \"image\": {\n        \"link\": \"{{ $json.property.image }}\"\n      }\n    },\n    \"body\": {\n      \"text\": {{ JSON.stringify($('Code in JavaScript2').item.json.replyText) }}\n    },\n    \"action\": {\n      \"buttons\": [\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $('Code in JavaScript2').item.json.payButtonId }}\",\n            \"title\": \"Pembayaran\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"{{ $('Code in JavaScript2').item.json.keeperButtonId }}\",\n            \"title\": \"Hubungi Penjaga\"\n          }\n        },\n        {\n          \"type\": \"reply\",\n          \"reply\": {\n            \"id\": \"ADMIN_HELP\",\n            \"title\": \"Ask Admin\"\n          }\n        }\n      ]\n    }\n  }\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1072,
        176
      ],
      "id": "5fa23d13-40e1-4560-a548-a8321876527b",
      "name": "HTTP Request11",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Detect Action').item.json.conversationId }}/labels",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"labels\": {{ JSON.stringify($('Code in JavaScript2').item.json.labelsToSend) }}\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1280,
        176
      ],
      "id": "c8942a1b-3f41-482a-953e-8bc3a67d021f",
      "name": "HTTP Request: Set Location Label in Chatwoot"
    },
    {
      "parameters": {
        "jsCode": "const currentLabels = $('Detect Action').first().json.labels || [];\nconst labels = [...new Set([...currentLabels, 'waiting_admin'])];\nreturn [\n  {\n    json: {\n      ...$json,\n      labelsToSend: labels\n    }\n  }\n];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        640,
        352
      ],
      "id": "673fbbfa-34e8-46ba-b8ae-ca1d167a5b3d",
      "name": "Code in JavaScript4"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Webhook').item.json.body.conversation.id }}/labels",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"labels\": {{ JSON.stringify($json.labelsToSend) }}\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        848,
        352
      ],
      "id": "a1ec47fb-e9c5-43ea-9c02-d7b1d1bc6586",
      "name": "HTTP Request15"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Webhook').item.json.body.conversation.id }}/assignments",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "{\n  \"assignee_id\": 1\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1056,
        352
      ],
      "id": "670cbe84-b040-4ae7-a3cf-b528b1e4f4fb",
      "name": "HTTP Request5"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Webhook').item.json.body.conversation.id }}/messages",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"content\": \"Baik kak, saya teruskan ke admin ya. Mohon tunggu sebentar 🙏, untuk buka menu silahkan ketik 'menu'\",\n  \"message_type\": \"outgoing\",\n  \"private\": false\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1248,
        352
      ],
      "id": "63f73318-7b96-417a-9a99-4d69ca3dc2ef",
      "name": "HTTP Request16"
    },
    {
      "parameters": {
        "jsCode": "const original = $('Detect Action').first().json;\nconst phone = original.phone;\nconst locTitle = original.selectedLocationTitle || 'HighlanderStay';\nconst locLabel = original.locationLabel || 'survey_general';\nconst currentLabels = (original.labels || []).filter(l => !l.startsWith('survey_'));\nconst labels = [...new Set([...currentLabels, locLabel, 'waiting_admin'])];\n\nconst paymentBody =\n`💳 *Informasi Pembayaran & Booking*\\nUnit: *${locTitle}*\\n\\nSilakan transfer pembayaran ke rekening resmi HighlanderStay:\\n\\n🏦 *Bank BCA*\\nNo. Rekening: *1980023539*\\nAtas Nama: *Lorentz Levanone*\\n\\n⚠️ *PENTING:*\\nPastikan nama penerima transfer adalah *Lorentz Levanone*.\\nKami tidak bertanggung jawab atas transfer selain ke rekening di atas.\\n\\n📸 *Konfirmasi Pembayaran:*\\nSilakan kirimkan *foto / screenshot bukti transfer* langsung ke chat ini. Tim admin kami akan segera membantu proses booking kamar kakak 🙏\\n\\n_(Ketik *menu* kapan saja untuk kembali ke menu utama)_`;\n\nreturn [\n  {\n    json: {\n      phone,\n      locTitle,\n      labelsToSend: labels,\n      whatsappPayload: {\n        messaging_product: 'whatsapp',\n        to: phone,\n        type: 'text',\n        text: {\n          preview_url: false,\n          body: paymentBody\n        }\n      }\n    }\n  }\n];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        640,
        512
      ],
      "id": "dbf24be3-75ec-4e78-bc57-010515e08b16",
      "name": "Code in JavaScript: Payment"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ $json.whatsappPayload }}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        848,
        512
      ],
      "id": "fb18c50e-3b5a-4cb7-a50d-bc0a72fa3dfb",
      "name": "HTTP Request: Send Payment Info",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Webhook').item.json.body.conversation.id }}/labels",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={\n  \"labels\": {{ JSON.stringify($('Code in JavaScript: Payment').item.json.labelsToSend) }}\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1056,
        512
      ],
      "id": "ee65ee39-fe29-450f-a496-e242d59bf3d6",
      "name": "HTTP Request: Set Payment Labels"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "=https://chatwoot.highlanderstay.com/api/v1/accounts/{{ $('Webhook').item.json.body.account.id }}/conversations/{{ $('Webhook').item.json.body.conversation.id }}/assignments",
        "sendHeaders": true,
        "headerParameters": {
          "parameters": [
            {
              "name": "Content-Type",
              "value": "application/json"
            },
            {
              "name": "api_access_token",
              "value": "ReFNYPtkGTHn7sFc3exzR6bn"
            }
          ]
        },
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "{\n  \"assignee_id\": 1\n}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1248,
        512
      ],
      "id": "e6749ca2-6b94-406b-b462-8e1241103b4a",
      "name": "HTTP Request: Assign Admin for Payment"
    },
    {
      "parameters": {
        "url": "https://dashboard.highlanderstay.com/api/v1/available-rooms",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.2,
      "position": [
        640,
        672
      ],
      "id": "b8f04c63-4712-4fb3-81b8-6a3c97db0e21",
      "name": "OpenKos: Fetch Keeper Info"
    },
    {
      "parameters": {
        "jsCode": "const original = $('Detect Action').first().json;\nconst phone = original.phone;\nlet selectedLocationId = String(original.selectedLocationId || '').trim();\n\n// Fallback from existing conversation label if selectedLocationId is empty\nif (!selectedLocationId || selectedLocationId === 'LOC_') {\n  const existingSurveyLabel = (original.labels || []).find(l => l.startsWith('survey_') && l !== 'survey_general');\n  if (existingSurveyLabel) {\n    const mapLabelToId = {\n      'survey_alpukat': 'LOC_ALPUKAT',\n      'survey_gpv': 'LOC_APARTEMEN_SEDAYU',\n      'survey_green_garden': 'LOC_GREEN_GARDEN',\n      'survey_greenville': 'LOC_GREENVILLE',\n      'survey_jelambar': 'LOC_JELAMBAR',\n      'survey_pakjo': 'LOC_PAKJO',\n      'survey_pedongkelan': 'LOC_PEDONGKELAN',\n      'survey_pesing_baru': 'LOC_PESING_BARU',\n      'survey_pesing_lama': 'LOC_PESING_LAMA',\n      'survey_rajawali': 'LOC_RAJAWALI',\n      'survey_sumur_bor': 'LOC_SUMUR_BOR',\n      'survey_taman_mahkota': 'LOC_TAMAN_MAHKOTA',\n      'survey_td_guest': 'LOC_TD_GUEST',\n      'survey_td647': 'LOC_TD647',\n      'survey_td795': 'LOC_TD795'\n    };\n    selectedLocationId = mapLabelToId[existingSurveyLabel] || `LOC_${existingSurveyLabel.replace(/^survey_/, '').toUpperCase()}`;\n  }\n}\n\nconst response = $input.first().json;\nconst items = Array.isArray(response.data) ? response.data : (Array.isArray(response) ? response : []);\n\nlet row = null;\nif (selectedLocationId) {\n  const target = selectedLocationId.replace(/^(LOC_|KEEPER_|PAY_)/, '').toLowerCase();\n  row = items.find(prop => {\n    const rawSlug = String(prop.slug || '').toLowerCase();\n    const rawName = String(prop.name || '').toLowerCase();\n    const idCol = `LOC_${(prop.slug || prop.name || '').toUpperCase().replace(/[^A-Z0-9_]/g, '_')}`.toLowerCase();\n    const targetIdCol = selectedLocationId.toLowerCase();\n\n    if (idCol === targetIdCol) return true;\n    if (rawSlug === target || rawName === target) return true;\n    if (target && target.length > 2 && (rawSlug.includes(target) || target.includes(rawSlug))) return true;\n    if (target && target.length > 2 && (rawName.includes(target) || target.includes(rawName))) return true;\n    return false;\n  });\n}\n\nconst title = row?.name || 'HighlanderStay';\nconst rawPhone = String(row?.phone || '-').trim();\nlet cleanPhone = rawPhone.replace(/\\D/g, '');\nif (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.slice(1);\n\nconst linkAlamat = row?.address_url || '-';\n\nconst waLink = cleanPhone ? `https://wa.me/${cleanPhone}?text=Halo%20Pak%2FBu%2C%20saya%20tertarik%20dengan%20kamar%20di%20${encodeURIComponent(title)}` : '-';\n\nconst keeperBody =\n`👤 *Kontak Penjaga Lokasi - ${title}*\\n\\nBerikut kontak penjaga yang bertugas di lokasi kos:\\n📱 No. WhatsApp: *${rawPhone}*\\n👉 *Klik untuk Chat Langsung:*\\n${waLink}\\n\\n📍 *Link Alamat Maps:*\\n${linkAlamat}\\n\\n_(Ketik *menu* kapan saja untuk kembali ke menu utama)_`;\n\nreturn [\n  {\n    json: {\n      phone,\n      title,\n      rawPhone,\n      cleanPhone,\n      waLink,\n      linkAlamat,\n      whatsappPayload: {\n        messaging_product: 'whatsapp',\n        to: phone,\n        type: 'text',\n        text: {\n          preview_url: true,\n          body: keeperBody\n        }\n      }\n    }\n  }\n];"
      },
      "type": "n8n-nodes-base.code",
      "typeVersion": 2,
      "position": [
        848,
        672
      ],
      "id": "d7bcfb92-75d8-4f24-9b2f-a9cb7061d3f9",
      "name": "Code in JavaScript: Keeper"
    },
    {
      "parameters": {
        "method": "POST",
        "url": "https://graph.facebook.com/v23.0/1183813914809639/messages",
        "authentication": "genericCredentialType",
        "genericAuthType": "httpBearerAuth",
        "sendBody": true,
        "specifyBody": "json",
        "jsonBody": "={{ $json.whatsappPayload }}",
        "options": {}
      },
      "type": "n8n-nodes-base.httpRequest",
      "typeVersion": 4.4,
      "position": [
        1056,
        672
      ],
      "id": "d0e12345-8888-4abc-def0-abcdef123456",
      "name": "HTTP Request: Send Keeper Info",
      "credentials": {
        "httpBearerAuth": {
          "id": "EV364nHUXp8nHCja",
          "name": "Bearer Auth account"
        }
      }
    }
  ],
  "connections": {
    "Webhook": {
      "main": [
        [
          {
            "node": "Detect Action",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Detect Action": {
      "main": [
        [
          {
            "node": "Switch",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Switch": {
      "main": [
        [
          {
            "node": "If",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "HTTP Request1",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "HTTP Request8",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "OpenKos: Fetch Available Rooms",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "OpenKos: Fetch Property Info",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Code in JavaScript4",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "Code in JavaScript: Payment",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "OpenKos: Fetch Keeper Info",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "If": {
      "main": [
        [
          {
            "node": "HTTP Request10",
            "type": "main",
            "index": 0
          }
        ],
        [
          {
            "node": "HTTP Request",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request10": {
      "main": [
        [
          {
            "node": "HTTP Request14",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request1": {
      "main": [
        [
          {
            "node": "HTTP Request17",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request8": {
      "main": [
        [
          {
            "node": "HTTP Request18",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenKos: Fetch Available Rooms": {
      "main": [
        [
          {
            "node": "Code in JavaScript1",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Code in JavaScript1": {
      "main": [
        [
          {
            "node": "HTTP Request9",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenKos: Fetch Property Info": {
      "main": [
        [
          {
            "node": "Code in JavaScript2",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Code in JavaScript2": {
      "main": [
        [
          {
            "node": "HTTP Request11",
            "type": "main",
            "index": 0
          },
          {
            "node": "HTTP Request: Set Location Label in Chatwoot",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Code in JavaScript4": {
      "main": [
        [
          {
            "node": "HTTP Request15",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request15": {
      "main": [
        [
          {
            "node": "HTTP Request5",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request5": {
      "main": [
        [
          {
            "node": "HTTP Request16",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Code in JavaScript: Payment": {
      "main": [
        [
          {
            "node": "HTTP Request: Send Payment Info",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request: Send Payment Info": {
      "main": [
        [
          {
            "node": "HTTP Request: Set Payment Labels",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "HTTP Request: Set Payment Labels": {
      "main": [
        [
          {
            "node": "HTTP Request: Assign Admin for Payment",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "OpenKos: Fetch Keeper Info": {
      "main": [
        [
          {
            "node": "Code in JavaScript: Keeper",
            "type": "main",
            "index": 0
          }
        ]
      ]
    },
    "Code in JavaScript: Keeper": {
      "main": [
        [
          {
            "node": "HTTP Request: Send Keeper Info",
            "type": "main",
            "index": 0
          }
        ]
      ]
    }
  }
}
