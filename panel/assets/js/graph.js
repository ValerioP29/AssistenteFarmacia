const ctx = document.getElementById('myChart').getContext('2d')
const myChart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    datasets: [{
      data: [10, 20, 15, 25, 30, 35, 40],
      lineTension: 0,
      backgroundColor: 'transparent',
      borderColor: '#007bff',
      borderWidth: 4,
      pointBackgroundColor: '#007bff'
    }]
  },
  options: {
    scales: {
      x: {
        grid: {
          display: true,
          color: '#e0e0e0'
        }
      },
      y: {
        grid: {
          display: true,
          color: '#e0e0e0'
        },
        beginAtZero: true
      }
    },
    plugins: {
      legend: { display: false },
      tooltip: { boxPadding: 3 }
    }
  }
})
