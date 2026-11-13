# Changelog

Um bloco por dia, do mais novo para o mais antigo. Formato fixo: `## AAAA-MM-DD` e uma linha `- ` por mudança (a tela do plugin lê este arquivo).

## 2026-11-13
- Na janela de detalhes do contato (clique no nome na lista), a tabela não fica mais colada nas abas
- Na aba Histórico do contato, o ícone vermelho de alerta de uma ação com erro virou um selo redondo: só o ícone, e ao passar o mouse abre a palavra Falhou
- Na aba Etapas, as condições têm um ícone de interrogação no selo do tipo, no lugar do ícone de divisão

## 2026-11-12
- O construtor da campanha e a aba Pré-visualizar têm zoom: quatro botões (menos, a porcentagem que volta a 100%, mais e ajustar tudo na tela), Ctrl + roda do mouse e os atalhos Ctrl + mais, menos e zero
- A página do contato mostra os selos de Email, SMS e HSM à esquerda do botão Editar, os mesmos da coluna Status da lista, e abrem ao passar o mouse
- As páginas do segmento e da campanha mostram os selos de status à esquerda do botão Editar: Ativo ou Inativo, e no segmento também global, filtros, não reconstruído e construção lenta; na campanha também reentrada
- Exportar e importar papéis e usuários entre instâncias do Mautic: o pacote sai cifrado, com as senhas dos usuários, e uma chave de 100 caracteres mostrada uma vez. A importação confere tudo antes de salvar e devolve os itens que não puderam entrar, com o motivo (só para administradores)
- Nas listas com barra de ferramentas, o campo de filtro fica quadrado também quando a lupa é o único botão, e o menu da engrenagem abre para a esquerda, sem sair da tela
- Um botão de sol ou lua fica ao lado esquerdo do sino de notificações: um clique abre a tela Aparência em uma janela, com as cores de destaque e os temas, e a escolha vale na hora. O botão fica escondido na própria página da conta
- Na aba Etapas, os selos têm o mesmo visual dos selos de status das listas; na lista de segmentos, a contagem de contatos usa o selo azul ou cinza translúcido
- A pré-visualização da campanha não reduz mais o texto a 80%: o texto do passo Esperar fica no tamanho normal
- A página da campanha tem uma nova aba Etapas, depois de Contatos, no lugar das abas Ações e Condições do Mautic: cada passo, de ação ou de condição, na ordem do fluxo, mostra um selo do tipo (raio roxo para ação, divisão laranja para condição) e três selos, concluídos, com falha e aguardando, cada um em % dos contatos que chegaram ao passo
- Um clique nos selos com falha ou aguardando lista os contatos daquele estado em uma tabela abaixo dos passos, com a barra de busca da lista de contatos
- Recalcular datas de um passo Esperar agora fica na aba Etapas, acima da tabela, quando ela mostra os contatos aguardando desse passo (só para quem pode editar a campanha)
- As contagens dos passos na aba Pré-visualizar não abrem mais o modal de contatos: os contatos de cada passo estão na aba Etapas
- O menu lateral fica escondido atrás de uma faixa fina com uma seta; ao passar o mouse sobre a faixa, o menu abre por cima da página e fecha quando o mouse sai

## 2026-11-11
- Na página do Email, Contagem de cliques e Contatos agora vêm primeiro e Estatísticas e Mapa ficam abaixo
- Nova aba Histórico no Email, na página e na tela de editar (a última aba): quem mexeu, quando, e cada campo com o valor antes e o valor depois
- Novo ícone do plugin: o curativo sobre o disco azul do Mautic, com sombra longa, no tile de Configurações > Plugins e na lista de plugins. Desenho "Band Aid" da coleção Hospital Set, do SVG Repo (svgrepo.com/svg/2095/band-aid), licença CC0. Obrigado a quem desenhou

## 2026-11-10
- Na lista de contatos, o ícone vermelho de Não entre em contato que o plugin do WhatsApp mostra ao lado do nome some, como os de Email e SMS. A coluna Status continua mostrando Email, SMS e HSM

## 2026-11-09
- O menu de três pontos de cada contato da lista tem a opção Preferências, que abre o mesmo modal da página do contato, para ativar ou desativar Email e SMS do contato sem sair da lista
- Ao salvar, o modal fecha e você continua na lista, com a busca e a página que estava usando, e a coluna Status mostra o novo estado

## 2026-11-06
- Os modais do contato, do segmento e dos contatos aguardando de um passo da campanha quebram a linha de um valor longo sem espaços (um código, um link) dentro da célula; antes a tabela ficava com milhares de pixels e saía do modal

## 2026-11-05
- A lista de contatos tem, na engrenagem, a opção vermelha Excluir em lote, para excluir milhares de contatos de uma vez (por exemplo, depois de uma importação errada)
- A tela mostra a tabela de contatos com a barra de busca; a barra é a busca, a mesma da lista de contatos, e o total encontrado aparece em um alerta vermelho antes de excluir
- O botão vermelho Excluir fica no fim da barra e só liga depois que a busca é contada; a busca vazia não é aceita
- A confirmação é o modal do Mautic e pede para digitar a palavra EXCLUIR (DELETE em inglês); só clicar não basta
- A exclusão roda de 200 em 200, com uma barra de progresso no lugar do formulário; no fim, um alerta verde e a notificação dizem quantos contatos foram excluídos
- Como na lista de contatos, os visitantes anônimos ficam de fora da busca, da contagem e da exclusão, a menos que a busca peça is:anonymous
- Só quem pode excluir contatos de outros usuários vê a opção

## 2026-11-04
- A lista de Campos Personalizados tem Exportar definição e Importar definição no menu de opções, para clonar os campos entre instâncias
- A exportação mostra todos os campos de contatos e de empresas, ativos e inativos, com o status de cada um, e o botão Copiar avisa com uma notificação
- A importação mostra primeiro o que seria criado, ativado e inativado, sem salvar nada; depois de Confirmar, cria os campos que faltam e ativa ou inativa os que já existem
- Um campo que já existe mantém o rótulo e o tipo, e um campo identificador único (como o email) nunca é inativado; os segmentos que usam um campo inativado aparecem na conferência
- As telas de exportar e importar têm o botão Voltar
- A lista de Campos Personalizados tem a coluna Status, como segmentos e campanhas: ícone play verde ou stop vermelho, e Ativar / Desativar é o primeiro item do menu da linha; o campo email, que o Mautic não deixa inativar, fica com o ícone cinza e sem o item

## 2026-10-29
- A faixa de espera do node (no construtor e na aba Pré-visualizar) mostra, abaixo de "Esperar 3 dias", o horário e os dias programados: "Entre 10h e 19h", "Às 9h30m" e "Seg, Qua, Qui"; não precisa mais abrir o node para saber quando ele dispara
- A data de uma espera até uma data aparece no idioma e no fuso horário do usuário, em vez do inglês e do UTC do core
- O texto da espera começa com maiúscula ("Esperar 3 dias", "Esperar até ...") e o ícone da espera é uma ampulheta

## 2026-10-28
- A coluna Status das listas de segmentos, campanhas, usuários e emails mostra o status como um ícone (play verde, stop vermelho) que abre ao passar o mouse; Ativar e Desativar é o primeiro item do menu da linha
- O badge de status abre dentro da coluna, sem empurrar nem cobrir as outras, e os nomes não quebram de linha
- Campanhas que deixam o contato entrar de novo têm o selo azul Reingresso ao lado do status
- Os ícones do nome do segmento viram selos na coluna Status: Global (um globo), Filtros e os avisos vermelhos de segmento sem atualizar e de montagem lenta, com o número
- A lista de contatos tem a coluna Status com Email, SMS e HSM: verde quando o contato pode receber e vermelho quando está em Não Contatar do canal
- A lista de emails mostra o tipo (Campanha ou Broadcast) e os selos de Teste A/B, Tradução e Rascunho na coluna Status, cada um com a sua cor
- A notificação que aparece na tela (toast) fica escura e legível nos temas Escuro e Solarized Escuro
- Novo histórico de mudanças por dia (este arquivo), em uma aba própria do modal do plugin em Configurações > Plugins
- O plugin ganhou o tile "Mautic Patches" em Configurações > Plugins, já ligado e com o ícone do curativo (versão 0.6.0)

## 2026-10-27
- O item Atualizar de campanhas e segmentos aparece e some conforme o status muda, sem recarregar a página

## 2026-10-26
- Os botões de qualquer modal de confirmação ficam no rodapé
- O botão de adicionar das listas de valores do core fica abaixo da lista

## 2026-10-25
- Os tooltips dos botões dos nós da campanha são removidos só quando os botões entram na página

## 2026-10-24
- Contagens de cada nó da campanha com mais espaço; os tooltips dos botões do nó foram removidos
- Os status das listas de segmentos, campanhas, usuários e emails compartilham a mesma base, com mais testes

## 2026-10-23
- Status de Email como selo, com Ativar/Desativar no menu da linha
- Descrição escondida sob o nome nas listas de segmentos e campanhas
- Lista de segmentos: contagem de contatos em selo cinza/azul e linha "Atualizado em" maior
- Selo de status com fonte de 14px, medido de novo quando a fonte carrega
- Selos de status das listas do Mautic como .patches-badge, gêmeo do selo do n8n
- Os selos de template HSM e SMS passam a ser do plugin n8n-dispatch
- Correção: o tile do plugin acompanha o interruptor Ativo logo após salvar (bug do core)

## 2026-10-22
- Campanha, aba Preview: a ampulheta de um nó abre os contatos que aguardam nele, e o check abre os que concluíram, em modal largo e com busca
- Busca na lista de contatos de todas as tabelas do plugin, no mesmo lugar
- O modal de detalhes do segmento e os modais dos nós têm a mesma largura
- Filtros de data do segmento: mantêm as palavras relativas do core, trocam as em inglês e limpam o resto
- Nova ação de campanha Wait: segura o contato até uma data contada a partir de um campo de data
- Wait: "Recalcular datas" (simulação, aplicar e um comando), ícone de pausa e agendamento também após uma decisão
- Páginas de detalhe: botão "Mais >" mostra e esconde a coluna da direita
- Botões Detalhes do segmento e do contato usam o ícone "i"
- Lista de usuários: selo Ativo/Inativo e Desativar/Ativar no menu da linha

## 2026-10-21
- Formatação de campos de data e hora enquanto se digita
- Campanha, builder: cursor de mira sobre os círculos de conexão, botão de desconectar sobre o círculo com ícone de elo quebrado, linhas e círculos mais escuros no tema claro e arrastar o canvas com o botão esquerdo em um espaço vazio
- Ícone de carregamento, e não o do interruptor girando, enquanto um interruptor de lista trabalha
- Status de segmentos e campanhas como selo informativo, com Ativar/Desativar no menu da linha
- Título da página do segmento como PREFIXO - Nome
- Texto do botão voltar em pt_BR corrigido (Voltar ao %alvo%) e outros textos com marcadores errados
- Notificações fixas na tela e mais duradouras
- Lista de segmentos: o nome sem o alias
- Texto preto no hover vermelho do item Excluir dos menus, nos temas escuros

## 2026-10-20
- Chips de incluindo/excluindo na condição de campanha de Custom Objects
- Nova condição de campanha "Campos de contato": vários critérios em campos de contato e empresa
- Builder: botão de lixeira em cada linha para desconectar, e círculos dos nós acima do nó (70% para fora)
- O nó da condição de campos de contato no canvas diz o que é

## 2026-10-19
- Novo botão Atualizar para campanhas, como o dos segmentos
- O nome do segmento na lista abre um modal de detalhes, com botões no rodapé no visual do Mautic
- Modal de erro de validação do builder de email no visual do Mautic, com os erros em lista
- Modal de detalhes do contato: Editar, Detalhes e Fechar no rodapé
- Temas escuros: pontinhos das ações em massa do cabeçalho da lista legíveis

## 2026-10-16
- Correção: balão de espera duplicado no builder após editar um evento
- Builder e aba Preview: a espera aparece como uma faixa sobre o nó
- Canvas da campanha: arrastar com o botão do meio do mouse
- Faixa de espera: o caminho "não" de uma condição diz "esperar", não "entre"
- Formulário do segmento: botão "Duplicar condição" em cada filtro
- Temas escuros: os detalhes de uma linha expandida da Auditoria eram um bloco branco

## 2026-10-15
- Builder: o modal do evento (ações, condições, decisões) tem a largura do modal de detalhes do contato
- Formulário do segmento: os filtros primeiro e o seletor que adiciona um por último
- Modal do contato: sem segundo espaçamento ao redor da tabela de detalhes

## 2026-10-14
- Chips: botão de copiar, um valor por linha
- Campanha: Prefixo obrigatório (caixa de configuração, seleção no formulário, chave na API, coluna ordenável na lista)
- Origem de contatos da campanha: segmentos chamados PREFIXO - Nome no seletor, no nó do canvas, no filtro da lista, na aba Preview e no título da página

## 2026-10-13
- O nome público do segmento é sempre "PREFIXO - Nome"
- Segmento: "em" / "não em" em campos de texto de contato e empresa, com componente de chips
- Aba Filtros do segmento: valores incluídos e excluídos como selos
- Lista de contatos: ícone girando no botão do modo ao vivo enquanto ele está ligado

## 2026-10-12
- O nome do segmento exige o prefixo (campo dividido e validação no formulário e na API)
- O prefixo do segmento é escolhido de uma lista cadastrada em Configurações > Configurações de segmentos
- O prefixo é uma coluna própria, ordenável no servidor
- O alias do segmento, quando não digitado, é o prefixo e o nome em minúsculas, só letras e números

## 2026-10-10
- Abas de contatos do segmento e da campanha com a tabela da lista de contatos
- Página do segmento: o gráfico de estatísticas fica abaixo dos contatos e o filtro de contatos abaixo do gráfico
- Página do segmento: aba Filtros, a primeira e aberta por padrão, com os filtros que o segmento aplica

## 2026-10-09
- Lista de contatos: a opção Ver e o nome do contato abrem a tabela de detalhes em um modal, e Detalhes abre em nova aba
- Temas escuros: fundo legível nas linhas marcadas como novas

## 2026-10-07
- Página do contato: o bloco Detalhes abre por padrão e um botão esconde ou mostra a coluna da direita (sempre começa escondida)
- Atualizar do segmento: o botão clicado fica travado, com um carregando, até a atualização terminar (rota de status e consulta periódica), com "Atualizando..." na linha "Atualizado em"
- Monitor de desempenho: tempo da API (Server-Timing e log do dia), tela Desempenho, relatório e comandos de diagnóstico
- Tela Desempenho: "Executar diagnóstico" trava o botão e atualiza a tela com as verificações
- O log de desempenho guarda os nomes dos parâmetros da consulta e o tamanho da resposta

## 2026-10-03
- Linhas de tabela mais escuras no tema claro

## 2026-10-02
- Linhas de tabela listradas brancas corrigidas nos temas escuros
- Nova API POST /api/segments/{id}/rebuild: recalcula um segmento agora, em segundo plano
- Item Atualizar no menu da linha da lista de segmentos e no menu da página do segmento, com mensagem curta "O segmento está sendo atualizado"
- Lista de segmentos: mostra quando cada segmento foi atualizado, na língua do usuário
- Temas escuros: hover de Salvar e Fechar / Cancelar sem texto branco sobre branco; colunas brancas seguem o tema
- Os botões dos modais dividem toda a largura do rodapé
- Tema claro: cinza claro no lugar do branco puro e camadas e superfícies mais escuras
- Ícone próprio do plugin no lugar do genérico do Mautic
- Página da campanha: o Preview da jornada vem antes do bloco de estatísticas

## 2026-09-25
- Criação do Mautic Patches: correção de um bug do overlay de "Jump to Event"
- README com o propósito do repositório e suas correções
