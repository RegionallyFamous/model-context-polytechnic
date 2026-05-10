(() => {
  const courseEndpoint = 'https://joinmcpoly.com/mcp/wordpress-plugin-craft';
  const progressTool = 'model-context-polytechnic-wordpress-plugin-craft-get-progress';
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const stops = {
    freshman: {
      index: 0,
      level: 'LV 01',
      portrait: '01',
      sprite: 'A',
      rank: 'Freshman Pupil',
      title: 'Freshman Gate',
      state: 'Passed',
      copy: 'The learner has an anonymous enrollment key and is ready to study without WordPress credentials.',
      x: '18%',
      y: '72%',
      meter: 0,
    },
    workshop: {
      index: 1,
      level: 'LV 02',
      portrait: '02',
      sprite: 'B',
      rank: 'Workshop Apprentice',
      title: 'Workshop Hall',
      state: 'Passed',
      copy: 'The learner is practicing bootstrap discipline, lifecycle cleanup, security habits, and release checks.',
      x: '39%',
      y: '58%',
      meter: 25,
    },
    architecture: {
      index: 2,
      level: 'LV 03',
      portrait: '03',
      sprite: 'C',
      rank: 'Architecture Apprentice',
      title: 'Architecture Quad',
      state: 'Active',
      copy: 'The learner is separating WordPress hooks from testable domain logic with namespaces, prefixes, classes, and boundaries.',
      x: '58%',
      y: '46%',
      meter: 50,
    },
    graduation: {
      index: 3,
      level: 'LV 28',
      portrait: '28',
      sprite: 'P',
      rank: 'Professor of Plugin Craft',
      title: 'Graduation Tower',
      state: 'Locked',
      copy: 'The learner receives a certificate after every published exercise passes, then reflects on what changed.',
      x: '76%',
      y: '30%',
      meter: 100,
    },
  };

  const route = ['freshman', 'workshop', 'architecture', 'graduation'];
  const demoProgress = {
    completed_count: 2,
    total_exercise_count: 28,
    completion_percent: 7.14,
    exercises: [
      {
        exercise_slug: 'design-plugin-bootstrap',
        exercise_title: 'Design Plugin Bootstrap',
        best_score: 1,
        passed: true,
      },
      {
        exercise_slug: 'lifecycle-cleanup-plan',
        exercise_title: 'Lifecycle Cleanup Plan',
        best_score: 1,
        passed: true,
      },
    ],
  };

  const avatar = document.querySelector('.avatar');
  const avatarSprite = document.getElementById('avatar-sprite');
  const portrait = document.getElementById('portrait');
  const level = document.getElementById('level');
  const rankTitle = document.getElementById('rank-title');
  const rankCopy = document.getElementById('rank-copy');
  const stopState = document.getElementById('stop-state');
  const stopTitle = document.getElementById('stop-title');
  const stopCopy = document.getElementById('stop-copy');
  const meterFill = document.getElementById('meter-fill');
  const motionState = document.getElementById('motion-state');
  const registrarState = document.getElementById('registrar-state');
  const badgeCount = document.getElementById('badge-count');
  const badges = document.getElementById('badges');
  const questList = document.getElementById('quest-list');
  const form = document.getElementById('progress-form');
  const endpointInput = document.getElementById('endpoint');
  const enrollmentKeyInput = document.getElementById('enrollment-key');

  let currentStop = 'architecture';
  let currentProgress = demoProgress;
  let journeyTimer = null;
  let isWalking = false;

  function numberFromPercent(value) {
    return Number.parseFloat(value.replace('%', ''));
  }

  function stopForProgress(progress) {
    const total = Math.max(Number(progress.total_exercise_count || 0), 1);
    const completed = Number(progress.completed_count || 0);
    const ratio = completed / total;

    if (completed >= total) {
      return 'graduation';
    }

    if (ratio >= 0.5) {
      return 'graduation';
    }

    if (ratio >= 0.12) {
      return 'architecture';
    }

    if (completed > 0) {
      return 'workshop';
    }

    return 'freshman';
  }

  function rankForProgress(progress, stop) {
    const completed = Number(progress.completed_count || 0);
    const total = Number(progress.total_exercise_count || 0);
    const percent = Number(progress.completion_percent || 0);

    if (completed >= total && total > 0) {
      return {
        level: `LV ${String(total).padStart(2, '0')}`,
        rank: 'Professor of Plugin Craft',
        copy: 'Every published exercise has a passing attempt. The learner is ready for commencement and reflection.',
      };
    }

    if (completed >= 12) {
      return {
        level: `LV ${String(completed).padStart(2, '0')}`,
        rank: 'Senior Plugin Builder',
        copy: `The learner has passed ${completed} labs and is moving toward capstone-level judgment.`,
      };
    }

    if (completed >= 3) {
      return {
        level: `LV ${String(completed).padStart(2, '0')}`,
        rank: 'Architecture Apprentice',
        copy: `The learner has passed ${completed} labs and is learning cleaner WordPress boundaries.`,
      };
    }

    if (completed > 0) {
      return {
        level: `LV ${String(completed).padStart(2, '0')}`,
        rank: 'Workshop Apprentice',
        copy: `The learner has passed ${completed} of ${total} labs (${percent.toFixed(2)}%). The workshop stamps are beginning to collect.`,
      };
    }

    return {
      level: stops[stop].level,
      rank: stops[stop].rank,
      copy: stops[stop].copy,
    };
  }

  function setAvatarPosition(key) {
    const stop = stops[key];
    avatar.style.left = stop.x;
    avatar.style.top = stop.y;
  }

  function updateStops(activeKey) {
    document.querySelectorAll('[data-stop]').forEach((button) => {
      const key = button.dataset.stop;
      const stop = stops[key];

      if (key === activeKey) {
        button.dataset.state = 'active';
      } else if (stop.index < stops[activeKey].index) {
        button.dataset.state = 'passed';
      } else {
        button.dataset.state = 'locked';
      }
    });
  }

  function updatePanel(key) {
    const stop = stops[key];
    const rank = rankForProgress(currentProgress, key);
    const total = Number(currentProgress.total_exercise_count || 0);
    const completed = Number(currentProgress.completed_count || 0);
    const percent = Number(currentProgress.completion_percent || 0);

    avatarSprite.textContent = stop.sprite;
    portrait.textContent = stop.portrait;
    level.textContent = rank.level;
    rankTitle.textContent = rank.rank;
    rankCopy.textContent = rank.copy;
    stopState.textContent = stop.state;
    stopTitle.textContent = stop.title;
    stopCopy.textContent = stop.copy;
    meterFill.style.width = `${Math.min(Math.max(percent, 0), 100)}%`;
    badgeCount.textContent = `${completed} / ${total || 28} labs`;
    updateStops(key);
    renderBadges(currentProgress);
    renderQuests(currentProgress);
  }

  function renderBadges(progress) {
    const exercises = progress.exercises || [];

    if (exercises.length === 0) {
      badges.innerHTML = '<div class="badge"><strong>Enrollment Card</strong>No labs passed yet. Begin class to unlock badges.</div>';
      return;
    }

    const recent = exercises.slice(0, 6).map((exercise) => {
      const score = Number(exercise.best_score || 0);
      const scoreLabel = `${Math.round(score * 100)}%`;
      return `<div class="badge unlocked"><strong>${escapeHtml(exercise.exercise_title || exercise.exercise_slug)}</strong>Passed with ${scoreLabel}.</div>`;
    });

    badges.innerHTML = recent.join('');
  }

  function renderQuests(progress) {
    const exercises = progress.exercises || [];
    const completed = Number(progress.completed_count || 0);
    const total = Number(progress.total_exercise_count || 0);

    const passed = exercises.slice(0, 4).map((exercise) => {
      const score = Math.round(Number(exercise.best_score || 0) * 100);
      return `<div class="quest"><span>OK</span><span>${escapeHtml(exercise.exercise_title || exercise.exercise_slug)}<small>Score: ${score}%</small></span></div>`;
    });

    const next = completed >= total && total > 0
      ? '<div class="quest"><span>GO</span><span>Commencement unlocked<small>Call get-certificate and deliver the reflection.</small></span></div>'
      : '<div class="quest"><span>GO</span><span>Next class awaits<small>Continue with the MCP tool calls returned by the course.</small></span></div>';

    questList.innerHTML = [...passed, next].join('');
  }

  function selectStop(key) {
    if (!stops[key]) {
      return;
    }

    clearJourneyTimer();
    currentStop = key;
    setAvatarPosition(key);
    updatePanel(key);
    motionState.textContent = 'Ready';
  }

  function clearJourneyTimer() {
    window.clearTimeout(journeyTimer);
    journeyTimer = null;
    isWalking = false;
    avatar.classList.remove('walking');
  }

  function walkToStop(key, done) {
    const stop = stops[key];
    const fromX = numberFromPercent(avatar.style.left || stops[currentStop].x);
    const fromY = numberFromPercent(avatar.style.top || stops[currentStop].y);
    const toX = numberFromPercent(stop.x);
    const toY = numberFromPercent(stop.y);
    const duration = reduceMotion ? 1 : 1500;
    const start = performance.now();

    isWalking = true;
    avatar.classList.add('walking');
    motionState.textContent = 'Walking';

    function step(now) {
      if (!isWalking) {
        return;
      }

      const elapsed = Math.min((now - start) / duration, 1);
      const eased = elapsed < 0.5 ? 2 * elapsed * elapsed : 1 - ((-2 * elapsed + 2) ** 2) / 2;
      const x = fromX + (toX - fromX) * eased;
      const y = fromY + (toY - fromY) * eased;

      avatar.style.left = `${x}%`;
      avatar.style.top = `${y}%`;

      if (elapsed < 1) {
        window.requestAnimationFrame(step);
        return;
      }

      currentStop = key;
      updatePanel(key);
      avatar.classList.remove('walking');
      isWalking = false;
      motionState.textContent = key === 'graduation' ? 'Preview' : 'Arrived';

      if (done) {
        done();
      }
    }

    window.requestAnimationFrame(step);
  }

  function nextRouteKey() {
    const index = stops[currentStop].index;
    return route[Math.min(index + 1, route.length - 1)];
  }

  function playJourney() {
    clearJourneyTimer();
    currentStop = 'freshman';
    setAvatarPosition(currentStop);
    updatePanel(currentStop);

    function advance() {
      const next = nextRouteKey();

      if (next === currentStop) {
        motionState.textContent = 'Complete';
        return;
      }

      walkToStop(next, () => {
        journeyTimer = window.setTimeout(advance, reduceMotion ? 1 : 900);
      });
    }

    journeyTimer = window.setTimeout(advance, reduceMotion ? 1 : 500);
  }

  function nextStep() {
    clearJourneyTimer();
    walkToStop(nextRouteKey());
  }

  async function callMcpTool(endpoint, tool, args) {
    const initResponse = await window.fetch(endpoint, {
      method: 'POST',
      headers: {
        Accept: 'application/json, text/event-stream',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        jsonrpc: '2.0',
        id: 1,
        method: 'initialize',
        params: {
          protocolVersion: '2025-06-18',
          capabilities: {},
          clientInfo: {
            name: 'mcpoly-learner-journey-viewer',
            version: '0.1.0',
          },
        },
      }),
    });

    if (!initResponse.ok) {
      throw new Error(`MCP initialize failed with HTTP ${initResponse.status}`);
    }

    const sessionId = initResponse.headers.get('mcp-session-id');
    const headers = {
      Accept: 'application/json, text/event-stream',
      'Content-Type': 'application/json',
    };

    if (sessionId) {
      headers['Mcp-Session-Id'] = sessionId;
    }

    const toolResponse = await window.fetch(endpoint, {
      method: 'POST',
      headers,
      body: JSON.stringify({
        jsonrpc: '2.0',
        id: 2,
        method: 'tools/call',
        params: {
          name: tool,
          arguments: args,
        },
      }),
    });

    if (!toolResponse.ok) {
      throw new Error(`MCP tool call failed with HTTP ${toolResponse.status}`);
    }

    const json = await toolResponse.json();
    const contentText = json?.result?.content?.find((item) => item.type === 'text')?.text;

    return json?.result?.structuredContent || (contentText ? JSON.parse(contentText) : null);
  }

  function applyProgress(progress, sourceLabel) {
    currentProgress = progress;
    currentStop = stopForProgress(progress);
    setAvatarPosition(currentStop);
    updatePanel(currentStop);
    registrarState.textContent = sourceLabel;
    motionState.textContent = 'Loaded';
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  document.querySelectorAll('[data-stop]').forEach((button) => {
    button.addEventListener('click', () => selectStop(button.dataset.stop));
  });

  document.getElementById('play-journey').addEventListener('click', playJourney);
  document.getElementById('pause-journey').addEventListener('click', () => {
    clearJourneyTimer();
    motionState.textContent = 'Paused';
  });
  document.getElementById('next-step').addEventListener('click', nextStep);
  document.getElementById('reset-journey').addEventListener('click', () => {
    applyProgress(demoProgress, 'Demo');
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const endpoint = endpointInput.value.trim() || courseEndpoint;
    const enrollmentKey = enrollmentKeyInput.value.trim();

    if (!enrollmentKey) {
      registrarState.textContent = 'Need key';
      enrollmentKeyInput.focus();
      return;
    }

    registrarState.textContent = 'Loading';

    try {
      const progress = await callMcpTool(endpoint, progressTool, {
        enrollment_key: enrollmentKey,
      });

      if (!progress) {
        throw new Error('The MCP response did not include progress data.');
      }

      applyProgress(progress, 'Live');
    } catch (error) {
      registrarState.textContent = 'Blocked';
      motionState.textContent = 'Demo';
      stopCopy.textContent = `${error.message} Demo progress remains visible.`;
    }
  });

  applyProgress(demoProgress, 'Demo');
})();
