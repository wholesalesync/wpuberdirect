<h3>Uber Dashboard</h3>
<div id="uber-container">
	<div id="uber-filters">
		<a href="#" class="change-status active" data-status="pending">Pending</a>
		<a href="#" class="change-status" data-status="pickup">Pickup</a>
		<a href="#" class="change-status" data-status="pickup_complete">Pickup complete</a>
		<a href="#" class="change-status" data-status="dropoff">Dropoff</a>
		<a href="#" class="change-status" data-status="delivered">Delivered</a>
		<a href="#" class="change-status" data-status="canceled">Canceled/Returned</a>
		<!-- <a href="#" class="change-status" data-status="returned">Returned</a> -->
		<a href="#" class="change-status" data-status="ongoing">Ongoing</a>
	</div>
	<div id="result">
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th>Delivery ID</th>
					<th>Created</th>
					<th>Pickup</th>
					<th>Dropoff</th>
					<th>Tracking url</th>
					<th>Status</th>
					<th>Fee</th>
				</tr>
			</thead>
			<tbody><tr colspan="7"></tr></tbody>
		</table>
		<img style="display:none;" src="<?php echo plugins_url( 'assets/img/loader.gif', dirname(__FILE__) ) ?>">
	</div>
</div>
<style type="text/css">
	.change-status.active {
	    background: #007cba;
	    color: white;
	    padding: 10px;
	    border-radius: 5px;
	    text-decoration: none;
	}
	#result {
		position: relative;
	}
	#result img {
		position: absolute;
		top: 100px;
		left: 50%;
		transform: translateX(-50%);
		max-width: 100px;
	}
	#wpcontent {
		background: #fff;
	}
	#uber-container table {
		width: 100%;
	}
	#uber-filters a {
	    margin-right: 10px;
	}
	#result {
	    min-height: 100px;
	    margin-top: 30px;
	    margin-right: 20px;
	}
	#result th {
		padding: 10px 0 10px 5px;
    	text-align: left;
	}
</style>