<table>
	<thead>
		<tr>
			<th><?php echo Connector::lang( '#' ); ?></th>
			<th><?php echo Connector::lang( 'Key' ); ?></th>
			<th><?php echo Connector::lang( 'Value' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php $index = 1; ?>
		<?php foreach( $rows as $key => $value ): ?>
			<tr>
				<td><?php echo $index ?></td>
				<td><?php echo $key ?></td>
				<td><?php echo $value ?></td>
			</tr>
			<?php $index++; ?>
		<?php endforeach; ?>
	</tbody>
</table>
