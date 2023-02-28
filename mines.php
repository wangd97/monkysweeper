<!DOCTYPE html>
<html>
	<head>
		<title>Monkysweeper</title>
		<link rel="icon" type="image/x-icon" href="favicon.ico">
		<link rel="stylesheet" href="lib/style.css">
		<script src="lib/jquery.js"></script>
	</head>

	<body>

		<div id="container">
			<table id="board"></table>
		</div>

		<script>
			$("#board").on("contextmenu", function() {return false;});

			// Set up the game board
			let board = [];
			let rows = 10;
			let cols = 10;
			let numMines = 10;
			let isGameStarted = false;
			let isGameOver = false;

			renderBoardBeforeGameStarts();

			// Generate the board with random mine placement
			function generateBoard(rows, cols, numMines, clickedRow, clickedCol) {

				// Helper array for determining random mine placement
				const mineOrder = [];

				// Generate each cell object
				for (let i = 0; i < rows; i++) {
					board[i] = [];
					for (let j = 0; j < cols; j++) {
						board[i][j] = {
							row: i,
							col: j,
							isMine: false,
							revealed: false,
							flagged: false,
							neighborMines: 0,
							neighborFlags: 0,
							neighbors: []
						};

						// Square is too close to starting click, disallow mine here
						if (Math.abs(i - clickedRow) <= 1 && Math.abs(j - clickedCol) <= 1) continue;

						// Allow a mine here
						mineOrder.push(board[i][j]);
					}
				}

				// Shuffle mineOrder and pick the first N cells to have a mine
				shuffleArray(mineOrder);
				mineOrder.slice(0, numMines).forEach(cell => {cell.isMine = true});
			}

			// Shuffle an array in place; used for randomizing mine locations
			function shuffleArray(array) {
				for (let i = array.length - 1; i > 0; i--) {
					const j = Math.floor(Math.random() * (i + 1));
					[array[i], array[j]] = [array[j], array[i]];
				}
			}

			// Initialize each square with the number of adjacent mines
			function initializeNeighbors() {
				for (let i = 0; i < rows; i++) {
					for (let j = 0; j < cols; j++) {

						// Square cannot itself be a mine
						if (board[i][j].isMine) continue;

						// Check the neighboring squares
						for (let iShift = -1; iShift <= 1; iShift++) {
							for (let jShift = -1; jShift <= 1; jShift++) {
								const row = i + iShift;
								const col = j + jShift;

								// If this is a legal square:
								if (row >= 0 && row < rows && col >= 0 && col < cols) {

									// Add this square to the list of neighbors
									board[i][j].neighbors.push(board[row][col]);

									// If mine, add to neighborMines
									if (board[row][col].isMine) {
										board[i][j].neighborMines++;
									}
								}
							}
						}
					}
				}
			}

			// Update the number of neighboring flags for every cell
			function updateNeighborFlags() {
				for (let i = 0; i < rows; i++) {
					for (let j = 0; j < cols; j++) {
						board[i][j].neighborFlags = board[i][j].neighbors.map(cell => cell.flagged ? 1 : 0).reduce((a, b) => a + b, 0);
					}
				}
			}

			// Handle clicking on a square
			function handleLeftClick(i, j) {

				// Game must not be over
				if (isGameOver) return;

				// If the game has not started yet, generate the board
				if (!isGameStarted) {
					generateBoard(rows, cols, numMines, i, j);
					initializeNeighbors();
					isGameStarted = true;
				}
				// If square is flagged: do nothing
				if (board[i][j].flagged) return;

				// If square is revealed and blank: do nothing
				if (board[i][j].revealed && board[i][j].neighborMines == 0) return;

				// If square is revealed and has a number:
				if (board[i][j].revealed && board[i][j].neighborMines > 0) {
					// If Flags == Mines, then can reveal surrounding squares
					if (board[i][j].neighborFlags == board[i][j].neighborMines) {
						board[i][j].neighbors.filter(cell => !cell.flagged && !cell.revealed).forEach(cell => handleLeftClick(cell.row, cell.col));
					}
					return;
				}

				// If square is not revealed:
				if (!board[i][j].revealed) {

					// Reveal the square
					board[i][j].revealed = true;

					// Check for game over
					if (board[i][j].isMine) {
						gameOver();
						return;
					}

					// If the square has no neighboring mines, reveal its neighbors
					if (board[i][j].neighborMines === 0) {
						board[i][j].neighbors.filter(cell => !cell.revealed).forEach(cell => handleLeftClick(cell.row, cell.col));
					}

					// Check for victory
					if (checkVictory()) {
						victory();
					}
				}
			}

			// Handle right-clicking to flag a square
			function handleRightClick(i, j) {
				// Game must not be over
				if (isGameOver) return;

				// Game must be started
				if (!isGameStarted) return;

				// Cannot flag a revealed square
				if (board[i][j].revealed) return;

				// Toggle the flagged status of the square
				board[i][j].flagged = !board[i][j].flagged;
				updateNeighborFlags();
			}

			// Handle game over
			function gameOver(clickedRow, clickedCol) {
				isGameOver = true;
				// Show all the mines and reveal the game over message
					for (let i = 0; i < rows; i++) {
						for (let j = 0; j < cols; j++) {
							if (board[i][j].isMine) {
								board[i][j].revealed = true;
							}
						}
					}
					alert('Game over!');
			}

			// Check if all non-mine squares are revealed
			function checkVictory() {
				for (let i = 0; i < rows; i++) {
					for (let j = 0; j < cols; j++) {
						if (!board[i][j].isMine && !board[i][j].revealed) {
							return false;
						}
					}
				}
				return true;
			}

			// Handle victory
			function victory() {
				isGameOver = true;
				// Reveal all the squares and display the victory message
				for (let i = 0; i < rows; i++) {
					for (let j = 0; j < cols; j++) {
						if (board[i][j].isMine) {
							board[i][j].flagged = true;
						}
						else {
							board[i][j].revealed = true;
						}
					}
				}
				alert('Congratulations, you won!');
			}

			function renderBoardBeforeGameStarts() {
				// Draw table
				$("#board").empty();
				for (let i = 0; i < rows; i++) {
					let $tr = $('<tr></tr>');
					$("#board").append($($tr));
					for (let j = 0; j < cols; j++) {
						let $td = $('<td class="square covered" data-row="' + i + '" data-col="' + j + '"></td>');
						$tr.append($($td));
					}
				}

				// Set up event listeners for each square
				$('.square').each((index, square) => {
					const i = parseInt($(square).attr('data-row'));
					console.log(i);
					const j = parseInt($(square).data('col'));

					$(square).on('mousedown', (e) => {
						const LEFT_CLICK = 1;
						const RIGHT_CLICK = 3;
						if (e.which == LEFT_CLICK) {
							handleLeftClick(i, j);
							renderBoardUpdate();
						}
						if (e.which == RIGHT_CLICK) {
							handleRightClick(i, j);
							renderBoardUpdate();
						}
					});
				});
			}

			// Render board HTML
			function renderBoardUpdate() {

				// Game must be started
				if (!isGameStarted) return;

				// For every cell:
				for (let i = 0; i < rows; i++) {
					for (let j = 0; j < cols; j++) {
						// Find the td element (square) of this row and col
						const $td = $('td[data-row=' + i + '][data-col=' + j + ']');
						$td.attr('class', 'square');

						// Render visual state of this square
						if (board[i][j].revealed) {
							$td.addClass('revealed');
							if (!board[i][j].isMine && board[i][j].neighborMines > 0 && board[i][j].neighborFlags > board[i][j].neighborMines)
								$td.addClass('overflagged');
							if (board[i][j].isMine)
								$td.addClass('mine');
						}
						if (!board[i][j].revealed) {
							$td.addClass('covered');
							if (!board[i][j].revealed && board[i][j].flagged)
								$td.addClass('flagged');
						}

						// Render hint number of this square
						if (board[i][j].revealed && board[i][j].neighborMines > 0) {
							$td.html(board[i][j].neighborMines);
							$td.css('color', 'var(--color' + board[i][j].neighborMines + ')');
						}
					}
				}
			}

				
		</script>

	</body>
</html>
