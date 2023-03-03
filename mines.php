<!DOCTYPE html>
<html>
	<head>
		<title>Monkysweeper</title>
		<style>
			body{cursor: url('images/banana-covered-cursor.png'), default;}
		</style>
		<link rel="icon" type="image/x-icon" href="favicon.ico">
		<link rel="stylesheet" href="lib/style.css">
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@200;300&family=Shantell+Sans:wght@300&family=Tilt+Neon&display=swap" rel="stylesheet">
		<script src="lib/jquery.js"></script>
		<script src="lib/seedrandom.js"></script>
		<script>Math.seedrandom(0);</script>
	</head>

	<body>

		<div id="container">
			<table id="board"></table>
			<div id="info"></div>
			<div id="timer" style="color:white"></div>
		</div>


		<script>









			/*
      var audio = new Audio('audio/Roblox-death-sound.mp3');
			let startTime; // The timestamp when the timer started
			let elapsedTime = 0; // The total elapsed time in milliseconds
			let timerInterval; // The ID of the setInterval() function that updates the timer

			function startTimer () {
				startTime = Date.now(); // Record the current timestamp
				timerInterval = setInterval(updateTimer, 100); // Update the timer every 100 milliseconds
			}

			function stopTimer () {
				clearInterval(timerInterval); // Stop the setInterval() function
			}

			function updateTimer () {
				// Calculate the elapsed time since the timer started
				const currentTime = Date.now();
				elapsedTime = currentTime - startTime;

				// Update the timer display with the new elapsed time
				const timerDisplay = document.getElementById('timer');
				timerDisplay.innerText = formatTime(elapsedTime);
			}

			function formatTime (time) {
				// Format the time in the format mm:ss
				const minutes = Math.floor(time / 60000);
				const seconds = Math.floor((time % 60000) / 1000);
				return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
			}
			*/



			$("#board").on("contextmenu", function() {return false;});

			let boardData = {};
			let board = [];
			let allCells = [];

			// Reset all default data
			function cleanBoard () {
				boardData = {
					rows: 16,
					cols: 30,
					numMines: 170,
					numFlags: 0,
					isGameStarted: false,
					isGameOver: false,
					won: false,
					lost: false,
					// for making board deducible
					lastNPerturbanceChoices: ['', '', ''],
					maxRecursionDepth: 5
				};

				board = [];
				allCells = [];
				for (let i = 0; i < boardData.rows; i++) {
					board[i] = [];
					for (let j = 0; j < boardData.cols; j++) {
						board[i][j] = {
							row: i,
							col: j,
							isMine: false,
							revealed: false,
							flagged: false,
							neighborMines: 0,
							neighborFlags: 0,
							neighbors: [],
							exploded: false
						};
						allCells.push(board[i][j]);
					}
				}
				initNeighbors();
				updateAllCells();
			}

			function updateAllCells () {
				allCells = [];
				for (let i = 0; i < boardData.rows; i++) {
					for (let j = 0; j < boardData.cols; j++) {
						allCells.push(board[i][j]);
					}
				}
			}

			function startGame (clickedRow, clickedCol) {
				while (true) {
					cleanBoard();
					boardData.isGameStarted = true;
					if (initDeducibleMineLocations(clickedRow, clickedCol)) break;
				}
				handleLeftClick(clickedRow, clickedCol);
				renderBoard();
			}

			// Make the board deducible by simulating a playthrough, and making changes if necessary
			// Returns true if success, else returns false
			function initDeducibleMineLocations (clickedRow, clickedCol) {

				initRandomMineLocations(clickedRow, clickedCol);
				handleLeftClick(clickedRow, clickedCol);

				let iterations = 0;
				// Repeatedly deduce and perturb mines
				while (deduce() || perturb());

				// If failed to generate deducible board, return false
				if (!boardData.won) {
					return false;
				}

				// Remember mine locations of deducible board
				const mineCells = allCells.filter(c => c.isMine);

				// Reset board
				cleanBoard();
				boardData.isGameStarted = true;

				// Place mines on the board
				mineCells.forEach(c => {board[c.row][c.col].isMine = true});
				updateMineInfo();

				return true;
			}

			// Generate random mine locations
			// Avoid the clicked cell and its neighbors
			// Always returns true;
			function initRandomMineLocations (clickedRow, clickedCol) {
				const clickedCell = board[clickedRow][clickedCol];

				// Mines can be placed on any cell except for the clicked cell and its neighbors
				const possibleMineCells = allCells.filter(c => c !== clickedCell && !clickedCell.neighbors.includes(c));

				// Shuffle possibleMineCells and pick the first N cells to have a mine
				shuffleArray(possibleMineCells).slice(0, boardData.numMines).forEach(cell => {cell.isMine = true});

				updateMineInfo();

				return true;
			}

			// Shuffle an array in place
			// Returns the array (as an option to make syntax cleaner)
			function shuffleArray (array) {
				for (let i = array.length - 1; i > 0; i--) {
					const j = Math.floor(Math.random() * (i + 1));
					[array[i], array[j]] = [array[j], array[i]];
				}
				return array;
			}

			// init each square with the number of adjacent mines
			function initNeighbors () {
				for (const cell of allCells) {
					cell.neighbors = [];

					// Check the neighboring squares
					for (let rowShift = -1; rowShift <= 1; rowShift++) {
						for (let colShift = -1; colShift <= 1; colShift++) {
							const row = cell.row + rowShift;
							const col = cell.col + colShift;

							// Neighbor must be a legal cell:
							if (row < 0 || row >= boardData.rows || col < 0 || col >= boardData.cols) continue;

							// Neighbor must not be the cell itself
							if (row === cell.row && col === cell.col) continue;

							// Add this neighbor to the list of neighbors
							cell.neighbors.push(board[row][col]);
						}
					}
				}
			}

			// Update the total number of flags, and the number of neighboring flags for each cell
			function updateFlagInfo () {
				let numFlags = 0;
				for (cell of allCells) {
					if (cell.flagged) numFlags++;
					cell.neighborFlags = cell.neighbors.filter(n => n.flagged).length;
				}
				boardData.numFlags = numFlags;
			}

			// Update all the hint numbers
			function updateMineInfo () {
				for (cell of allCells) {
					cell.neighborMines = cell.neighbors.filter(n => n.isMine).length;
				}
			}

			// Handle clicking on a cell
			// Returns true if a change was made, false otherwise
			function handleLeftClick (row, col) {

				// Game must not be over
				if (boardData.isGameOver) return false;

				// If the game has not started yet, generate the board
				if (!boardData.isGameStarted) {
					startGame(row, col);
					return true;
				}

				const cell = board[row][col];

				// If square is already revealed
				if (cell.revealed) {
					// If Flags == Mines, then can reveal surrounding squares
					let madeChanges = false;
					if (cell.neighborFlags == cell.neighborMines) {
						cell.neighbors.filter(neighborCell => !neighborCell.flagged && !neighborCell.revealed).forEach(neighborCell => {
							if (handleLeftClick(neighborCell.row, neighborCell.col)) madeChanges = true;
						});
					}
					return madeChanges;
				}

				// If square is flagged: do nothing
				if (cell.flagged) return false;

				// If square is not revealed and not flagged:
				if (!cell.revealed && !cell.flagged) {

					// Reveal the square
					cell.revealed = true;

					// Check for game over
					if (cell.isMine) {
						lose(row, col);
					}

					// If the square has no neighboring mines, reveal its neighbors
					if (cell.neighborMines == 0) {
						cell.neighbors.filter(cell => !cell.revealed).forEach(cell => handleLeftClick(cell.row, cell.col));
					}

					// Check for victory
					if (checkVictory()) {
						victory();
					}

					return true;
				}
			}

			// Handle right-clicking to flag a square
			// Returns true if a change was made, false otherwise
			function handleRightClick (row, col) {
				const cell = board[row][col];

				// Game must be started
				if (!boardData.isGameStarted) return false;

				// Cannot flag a revealed square
				if (cell.revealed) return false;

				// Toggle the flagged status of the square
				cell.flagged = !cell.flagged;
				updateFlagInfo();

				return true;
			}

			// Handle lose
			function lose (clickedRow, clickedCol) {
				boardData.isGameOver = true;
				boardData.lost = true;
				
				// Show all the mines locations, using flags
				board[clickedRow][clickedCol].revealed = true;
				board[clickedRow][clickedCol].exploded = true;

				// Game over message
        audio.play();
				console.log('Game over!');
			}

			// Check if all non-mine squares are revealed
			function checkVictory () {
				return allCells.filter(c => c.revealed).length === boardData.rows * boardData.cols - boardData.numMines;
			}

			// Handle victory
			function victory () {
				boardData.isGameOver = true;
				boardData.won = true;
				// Reveal all the squares and display the victory message
				for (const cell of allCells) {
					if (cell.isMine && !cell.flagged) {
						handleRightClick(cell.row, cell.col);
					}
					if (!cell.revealed && !cell.isMine) {
						handleLeftClick(cell.row, cell.col);
					}
				}

				console.log('Congratulations, you won!');
        window.open('https://www.youtube.com/watch?v=ymdhRMiMGK0&ab_channel=GrimGriefer', 'popup', config='height=375,width=450')
			}

			function renderBoard () {
				if (!boardData.isGameStarted) {
					renderBoardBeforeGameStarted();
				}
				else {
					renderBoardAfterGameStarted();
				}
				$('#info').html('Mines Remaining: ' + (boardData.numMines - boardData.numFlags));
			}

			// Create a fully covered board with event listeners
			function renderBoardBeforeGameStarted () {

				// Draw table
				$("#board").empty();
				for (let i = 0; i < boardData.rows; i++) {
					let $tr = $('<tr></tr>');
					$("#board").append($($tr));
					for (let j = 0; j < boardData.cols; j++) {
						let $td = $('<td class="square covered" data-row="' + i + '" data-col="' + j + '"></td>');
						$tr.append($($td));
					}
				}

				// Set up event listeners for each square
				$('.square').each((index, square) => {
					const LEFT_CLICK = 1;
					const RIGHT_CLICK = 3;

					const i = parseInt($(square).attr('data-row'));
					const j = parseInt($(square).attr('data-col'));

					$(square).on('mousedown', (e) => {
						if (e.which == LEFT_CLICK) {
							if (handleLeftClick(i, j)) {
								savestates.save();
								renderBoard();
							}
						}
						if (e.which == RIGHT_CLICK) {
							if (handleRightClick(i, j)) {
								savestates.save();
								renderBoard();
							}
						}
					});
				});
			}

			// Render board HTML
			function renderBoardAfterGameStarted () {

				for (const cell of allCells) {
					
					// Find the td element (square) of this row and col
					const $td = $('td[data-row=' + cell.row + '][data-col=' + cell.col + ']');

					// Reset classes
					$td.attr('class', 'square');

					// Render visual state of this square
					if (cell.revealed) {
						$td.addClass('revealed');
						if (!cell.isMine && cell.neighborMines > 0 && cell.neighborFlags > cell.neighborMines)
							$td.addClass('overflagged');
						if (cell.isMine)
							$td.addClass('mine').addClass('exploded');
					}
					if (!cell.revealed) {
						$td.addClass('covered');
						if (!cell.revealed && cell.flagged)
							$td.addClass('flagged');
					}

					// Render hint number of this square
					if (cell.revealed && !cell.isMine && cell.neighborMines > 0) {
						$td.html(cell.neighborMines);
						$td.css('color', 'var(--color' + cell.neighborMines + ')');
					}
					else {
						$td.empty();
					}
				}
			}






			// Undos

			$(document).keydown(function(e) {
				// Q
				if (e.which === 81) {
					deduce();
					renderBoard();
				}
				// W
				if (e.which === 87) {
					perturb();
					renderBoard();
				}
				// Ctrl + Z
				if (e.which === 90 && e.ctrlKey) {
					savestates.undo();
					renderBoard();
				}
				// Ctrl + Y
				if (e.which === 89 && e.ctrlKey) {
					savestates.redo();
					renderBoard();
				}
			});

			// Savestates
			let savestates = {
				states: [],
				index: -1,
				save: function() {
					this.index++;
					this.states[this.index] = {
						boardData: structuredClone(boardData),
						board: structuredClone(board)
					};
					this.states.length = this.index + 1;
				},
				undo: function() {
					if (this.index <= 0) return;
					this.index--;
					boardData = structuredClone(this.states[this.index].boardData);
					board = structuredClone(this.states[this.index].board);
					updateAllCells();
				},
				redo: function() {
					if (this.index + 1 >= this.states.length) return;
					this.index++;
					boardData = structuredClone(this.states[this.index].boardData);
					board = structuredClone(this.states[this.index].board);
					updateAllCells();
				}
			};




			let horizonHintCells = [];
			let horizonCoveredCells = [];

			function deduce () {
				// savestates.save();
				// console.log('q');
				// Setup
				if (!initSolver()) return false;
				// Deduce flags
				if (doEasyDeduction()) return true;
				if (doAutoComplete()) return true;
				if (doHardDeduction()) return true;
				// Fail to make a change
				return false;
			}

			function perturb () {
				// savestates.save();
				// console.log('w');
				// Setup
				if (!initSolver()) return false;
				// Perturb mines
				if (horizonHintCells.length > 0) {
					return randomlyFillOrClearHint();
				}
				// Fail to make a change
				return false;
			}

			function initSolver () {
				// Board must be generated (after the first click)
				if (!boardData.isGameStarted) return false;
				if (boardData.isGameOver) return false;

				// Add data to board
				boardData.numClusters = 0;
				for (const cell of allCells) {
					cell.isHorizon = false;
					cell.alwaysFlagged = true;
					cell.alwaysSafe = true;
					cell.inCluster = false;
					cell.clusterNumber = 0;
				}

				horizonHintCells = [];
				horizonCoveredCells = [];
				for (const cell of allCells) {
					// horizonHintCells: every cell that has a number and has a covered unflagged neighbor cell
					if (cell.revealed && cell.neighborMines > 0 && cell.neighbors.some(n => !n.revealed && !n.flagged)) {
						horizonHintCells.push(cell);
						cell.isHorizon = true;
					}
					// horizonCoveredCells: every covered cell that is neighboring an unfinished hint cell (including flagged cells)
					if (!cell.revealed && cell.neighbors.some(n => n.revealed && n.neighborMines - n.neighborFlags > 0)) {
						horizonCoveredCells.push(cell);
						cell.isHorizon = true;
					}
				}

				return true;
			}

			// Does easy flags + easy opens + cleanup
			// Returns true if changes are made, else returns false
			function doEasyDeduction () {
				let madeChanges = false;
				if (doEasyFlags()) madeChanges = true;
				if (doEasyOpens()) madeChanges = true;
				if (doCleanUp()) madeChanges = true;
				return madeChanges;
			}


			// Try every way to fill up horizon with flags
			// If a cell is unflagged in every configuration, then we can open it
			function doHardDeduction () {
				
				let madeChanges = false;
				const minFlagsNeededForCluster = [0];
				const completedClusters = [];

				// Deduce flags and safe cells for each cluster
				boardData.numClusters = findClusters();
				for (let cluster = 1; cluster <= boardData.numClusters; cluster++) {

					// deductionResult is usually the min total flags on the board when only this cluster is completed
					// deductionResult is -1 if it failed for this cluster (went beyond max recursion depth)
					const deductionResult = doHardDeductionHelper(cluster);
					if (deductionResult === -1) {
						minFlagsNeededForCluster[cluster] = 0;
					}
					else {
						completedClusters.push(cluster);
						minFlagsNeededForCluster[cluster] = deductionResult - boardData.numFlags;
					}
				}


				// If every configuration hits the flag limit, open all non-horizon cells
				if (boardData.numFlags + sum(minFlagsNeededForCluster) === boardData.numMines) {
					allCells.filter(c => !c.revealed && !c.flagged && c.alwaysSafe).forEach(c => {
						if (handleLeftClick(c.row, c.col)) madeChanges = true;
					})
				}
				
				// console.log('Always flagged: ' + horizonCoveredCells.filter(c => c.alwaysFlagged).map(c => '(' + c.row + ', ' + c.col + ')'));
				// console.log('Always safe: ' + horizonCoveredCells.filter(c => c.alwaysSafe).map(c => '(' + c.row + ', ' + c.col + ')'));

				// Flag and open cells according to which ones are "always flagged" and "always safe"
				// Only if this cluster succeeded the deduction phase
				for (const cell of horizonCoveredCells.filter(c => completedClusters.includes(c.clusterNumber))) {
					if (cell.alwaysFlagged && !cell.flagged) {
						if (handleRightClick(cell.row, cell.col)) madeChanges = true;
					}
					if (cell.alwaysSafe) {
						if (handleLeftClick(cell.row, cell.col)) madeChanges = true;
					}
					if (cell.alwaysFlagged && cell.alwaysSafe) {
						alert(cell.row + ' ' + cell.col + ' is always flagged and always safe?');
					}
				}
				return madeChanges;
			}

			// Recursively makes every valid flag guess for a cluster
			// Returns the minimum number of TOTAL flags when this cluster is completed
			// Returns -1 if the recursion was aborted for going too deep; ignore results if this happens
			function doHardDeductionHelper (whichCluster, depth) {
				if (typeof depth === 'number') {
					depth++;
				}
				else {
					depth = 1;
				}

				const horizonHintCellsThisCluster = horizonHintCells.filter(c => c.clusterNumber === whichCluster);
				const horizonCoveredCellsThisCluster = horizonCoveredCells.filter(c => c.clusterNumber === whichCluster);

				// Base case: if all hints satisfied and did not exceed flag limit: update "always flagged" and "always safe"
				if (horizonHintCellsThisCluster.every(cell => cell.neighborFlags === cell.neighborMines)) {
					if (boardData.numFlags > boardData.numMines) {
						return Infinity;
					}
					for (const cell of horizonCoveredCellsThisCluster) {
						if (cell.flagged) {
							cell.alwaysSafe = false;
						}
						else {
							cell.alwaysFlagged = false;
						}
					}
					return boardData.numFlags;
				}

				// Base case: if conflict occured: this mine configuration doesn't work
				if (horizonHintCellsThisCluster.some(cell => cell.neighborFlags > cell.neighborMines)) {
					return Infinity;
				}

				// Break if recursion is too deep
				if (depth >= boardData.maxRecursionDepth) {
					return -1;
				}

				let minTotalFlags = Infinity;
				// Typical case: for the first incomplete horizon hint cell, guess 1 flag next to it, then recurse
				// Do this for each cluster of cells (a cluster is a group of cells whose results affect each other)

				const cell = horizonHintCellsThisCluster.find(c => c.neighborFlags < c.neighborMines);
				for (const cellToFlag of cell.neighbors.filter(n => !n.revealed && !n.flagged)) {
					handleRightClick(cellToFlag.row, cellToFlag.col);
					minTotalFlags = Math.min(doHardDeductionHelper(whichCluster, depth), minTotalFlags);
					handleRightClick(cellToFlag.row, cellToFlag.col);
				}
				return minTotalFlags;
			}

			// Assign each horizon hint cell to a cluster number
			// Cells within a cluster can affect each other with their flags
			// Returns number of clusters found
			function findClusters () {
				let nextClusterNumber = 1;
				let cell;

				// While there exists any unclustered horizon hint cell
				while(cell = horizonHintCells.find(c => !c.inCluster)) {

					// Expand this cluster to all (recursively) neighboring horizon hint/covered cells
					expandCluster(cell, nextClusterNumber);
					nextClusterNumber++;
				}

				return nextClusterNumber - 1;
			}

			function printClusters () {
				for (let i = 0; i < board.length; i++) {
					string = '';
					for (let j = 0; j < board[i].length; j++) {
						string += board[i][j].clusterNumber;
					}
					console.log(string);
				}
			}

			function expandCluster (cell, clusterNumber) {

				// Assign this cell to a cluster
				cell.inCluster = true;
				cell.clusterNumber = clusterNumber;

				// Recurisvely expand to neighbors
				
				// For hint cells, neighboring hint/covered cells are in the same cluster
				if (cell.revealed) {
					for (neighborCell of cell.neighbors.filter(n => !n.inCluster && n.isHorizon && !n.flagged)) {
						expandCluster(neighborCell, clusterNumber)
					}
				}
				// For covered cells, only neighboring hint cells are in the same cluster
				else {
					for (neighborCell of cell.neighbors.filter(n => !n.inCluster && n.isHorizon && !n.flagged && n.revealed)) {
						expandCluster(neighborCell, clusterNumber);
					}
				}
			}

			// Put obvious flags (where a number N only has N covered cells around it)
			// If changes are made: return true; else return false
			function doEasyFlags () {
				let madeChanges = false;
				for (const cell of horizonHintCells) {
					// Find all covered neighbor cells
					const coveredNeighborCells = cell.neighbors.filter(n => !n.revealed);
					// If hint == # of covered neighbor cells
					if (cell.neighborMines === coveredNeighborCells.length) {
						// Flag all covered neighbor cells
						coveredNeighborCells.filter(n => !n.revealed && !n.flagged).forEach(n => {
							if (handleRightClick(n.row, n.col)) madeChanges = true;
						});
					}
				}
				return madeChanges;
			}

			// Click all numbers that have enough flags around it
			function doEasyOpens () {
				let madeChanges = false;
				allCells.filter(c => c.revealed && c.neighborMines === c.neighborFlags).forEach(c => {
					if (handleLeftClick(c.row, c.col)) madeChanges = true;
				});
				return madeChanges;
			}

			function doCleanUp () {
				let madeChanges = false;
				if (boardData.numFlags === boardData.numMines) {
					allCells.filter(c => !c.revealed && !c.flagged).forEach(c => {
						if (handleLeftClick(c.row, c.col)) madeChanges = true;
					});
				}
				return madeChanges;
			}

			// Find all hints that, when completed, automatically complete another hint
			function doAutoComplete () {
				let madeChanges = false;
				// For each hint:
				for (const cell of horizonHintCells) {
					// For each uncompleted neighbor hint
					for (const ncell of cell.neighbors.filter(n => n.revealed && n.neighborMines - n.neighborFlags > 0)) {
						// ncell must have equal or lesser effective value (hint - flags) compared to cell
						if (ncell.neighborMines - ncell.neighborFlags > cell.neighborMines - cell.neighborFlags) continue;
						// Calculate if completing cell autocompletes ncell
						// If (cell's covered unflagged neighbors which are not neighboring ncell === cell effective hint - ncell effective hint)
						// then cell autocompletes ncell
						if (cell.neighbors.filter(n => !n.revealed && !n.flagged && !ncell.neighbors.includes(n)).length === (cell.neighborMines - cell.neighborFlags) - (ncell.neighborMines - ncell.neighborFlags)) {
							for (const cellToFlag of cell.neighbors.filter(n => !n.revealed && !n.flagged && !ncell.neighbors.includes(n))) {
								if (handleRightClick(cellToFlag.row, cellToFlag.col)) madeChanges = true;
							}
							for (const cellToOpen of ncell.neighbors.filter(n => !n.revealed && !cell.neighbors.includes(n))) {
								if (handleLeftClick(cellToOpen.row, cellToOpen.col)) madeChanges = true;
							}
						}
					}
				}
				return madeChanges;
			}

			function cellToCoordinates (cell) {
				return '(' + cell.row + ', ' + cell.col + ')';
			}

			function getRandomElement (array) {
				return array[Math.floor(Math.random() * array.length)];
			}

			function sum (array) {
				return array.reduce((a, b) => a + b, 0);
			}

			function randomlyFillOrClearHint () {

				for (const hintCell of shuffleArray(horizonHintCells)) {
					const choices = ['fill', 'clear'];
					
					// coveredFarCells: covered unflagged cells that are not neighboring hintCell
					const coveredFarCells = allCells.filter(c => !c.revealed && !c.flagged && !hintCell.neighbors.includes(c));

					// If there are not enough cells to move mines away to, cannot choose clear
					const availableCellsForMines = coveredFarCells.filter(c => !c.isMine);
					const minesToRemove = hintCell.neighbors.filter(n => n.isMine);
					if (availableCellsForMines.length < minesToRemove.length) {
						choices.splice(choices.indexOf('clear'), 1);
					}

					// If there are not enough mines to bring to this hint, cannot choose fill
					const availableMinesToBring = coveredFarCells.filter(c => c.isMine);
					const cellsToAddMine = hintCell.neighbors.filter(n => !n.revealed && !n.isMine);
					if (availableMinesToBring.length < cellsToAddMine.length) {
						choices.splice(choices.indexOf('fill'), 1);
					}

					// Make choice of which action
					let choice;

					// Both choices are available
					if (choices.length === 2) {
						// If there is only 1 hint to work with, prefer clear (to avoid being boxed in)
						if (horizonHintCells.length === 1) choice = 'clear';
						// Don't pick the same choice N+1 times in a row
						else if (boardData.lastNPerturbanceChoices.every(s => s === 'fill')) choice = 'clear';
						else if (boardData.lastNPerturbanceChoices.every(s => s === 'clear')) choice = 'fill';
						// Pick randomly
						else choice = getRandomElement(choices);
					}
					// 1 choice available; pick it
					else if (choices.length === 1) {
						choice = choices[0];
					}
					// Cannot clear nor fill: try next horizon hint cell
					else {
						continue;
					}

					// Update history of choices
					boardData.lastNPerturbanceChoices.shift();
					boardData.lastNPerturbanceChoices.push(choice);

					// Surround hint with mines (only the covered cells)
					if (choice === 'fill') {
						let excessMines = 0;
						hintCell.neighbors.filter(n => !n.revealed).forEach(n => {
							if (!n.isMine) {
								n.isMine = true;
								excessMines++;
							}
						});

						// Remove excess mines
						shuffleArray(availableMinesToBring).slice(0, excessMines).forEach(c => {
							c.isMine = false;
						});
					}

					// Clear all mines around hint
					else if (choice === 'clear') {
						let mineDeficit = 0;
						hintCell.neighbors.filter(n => !n.revealed && !n.flagged && n.isMine).forEach(n => {
							n.isMine = false;
							mineDeficit++;
						});
						updateFlagInfo();

						// Add mines to make up deficit
						shuffleArray(availableCellsForMines).slice(0, mineDeficit).forEach(c => {
							c.isMine = true;
						})
					}

					else {
						alert('An impossible choice was made');
					}

					updateMineInfo();
					return true;
				}

				// Failed to do an action
				return false;
			}

			/*
			TODO: 
			- for fill and clear, have a preference for choosing to move mines that are on the horizon
			- after mines are generated, rerun solver to see if it's actually deducible (why isn't it??)


			*/



			cleanBoard();
			savestates.save();
			renderBoard();

				
		</script>

	</body>
</html>
