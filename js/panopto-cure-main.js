// Load the IFrame player API code asynchronously
const loadScript = () => {
  const tag = document.createElement('script');
  tag.src = "https://developers.panopto.com/scripts/embedapi.min.js";
  const firstScriptTag = document.getElementsByTagName('script')[0];
  firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
};

// Store all player instances
const players = {};

// Function to initialize a player
const initializePlayer = (playerId, sessionId) => {
  players[playerId] = new EmbedApi(playerId, {
    width: "750",
    height: "422",
    serverName: "midd.hosted.panopto.com",
    sessionId: sessionId,
    videoParams: {
      "interactivity": "all",
      "showtitle": "false",
    },
    events: {
      "onIframeReady": () => onPanoptoIframeReady(playerId),
      "onReady": () => onPanoptoVideoReady(playerId),
      "onStateChange": (state) => onPanoptoStateUpdate(playerId, state)
    }
  });
};

// The API will call this function when the iframe is ready
const onPanoptoIframeReady = (playerId) => {
  // The iframe is ready and the video is not yet loaded
  players[playerId].disableCaptions();
};

// The API will call this function when the video player is ready
const onPanoptoVideoReady = (playerId) => {
  // The video has successfully been loaded
  players[playerId].playVideo();
};

// The API calls this function when a player state change happens
const onPanoptoStateUpdate = (playerId, state) => {
  if (state === PlayerState.Playing) {
    players[playerId].setVolume(1);
    players[playerId].setPlaybackRate(1);
  }
};

// Initialize when API is ready
function onPanoptoEmbedApiReady() {
  // Find all player divs with data-session-id attribute
  const playerDivs = document.querySelectorAll('[data-session-id]');
  
  // Initialize each player
  playerDivs.forEach(div => {
    const playerId = div.id;
    const sessionId = div.getAttribute('data-session-id');
    
    if (playerId && sessionId) {
      initializePlayer(playerId, sessionId);
    }
  });
}

// Load the script
loadScript();