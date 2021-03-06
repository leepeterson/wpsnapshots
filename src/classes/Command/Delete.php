<?php

namespace WPSnapshots\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Question\Question;
use WPSnapshots\Connection;
use WPSnapshots\Utils;
use WPSnapshots\S3;


/**
 * This command deletes a snapshot from the repo given an ID.
 */
class Delete extends Command {

	/**
	 * Setup up command
	 */
	protected function configure() {
		$this->setName( 'delete' );
		$this->setDescription( 'Delete a snapshot from the repository.' );
		$this->addArgument( 'snapshot-id', InputArgument::REQUIRED, 'Snapshot ID to delete.' );
	}

	/**
	 * Execute command
	 *
	 * @param  InputInterface  $input
	 * @param  OutputInterface $output
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$connection = Connection::instance()->connect();

		if ( Utils\is_error( $connection ) ) {
			$output->writeln( '<error>Could not connect to repository.</error>' );
			return;
		}

		$id = $input->getArgument( 'snapshot-id' );

		$verbose = $input->getOption( 'verbose' );

		$snapshot = Connection::instance()->db->getSnapshot( $id );

		if ( Utils\is_error( $snapshot ) ) {
			$output->writeln( '<error>Could not get snapshot from database.</error>' );

			if ( is_array( $snapshot->data ) && ! empty( $snapshot->data['aws_error_code'] ) ) {
				if ( 'AccessDeniedException' === $snapshot->data['aws_error_code'] ) {
					$output->writeln( '<error>Access denied. You might not have access to this project.</error>' );
				}

				if ( $verbose ) {
					$output->writeln( '<error>Error Message: ' . $snapshot->data['message'] . '</error>' );
					$output->writeln( '<error>AWS Request ID: ' . $snapshot->data['aws_request_id'] . '</error>' );
					$output->writeln( '<error>AWS Error Type: ' . $snapshot->data['aws_error_type'] . '</error>' );
					$output->writeln( '<error>AWS Error Code: ' . $snapshot->data['aws_error_code'] . '</error>' );
				}
			}

			return;
		}

		$files_result = Connection::instance()->s3->deleteSnapshot( $id, $snapshot['project'] );

		if ( Utils\is_error( $files_result ) ) {
			if ( Utils\is_error( $files_result ) && $verbose ) {
				$output->writeln( '<error>S3 delete error:</error>' );
				$output->writeln( '<error>Error Message: ' . $files_result->data['message'] . '</error>' );
				$output->writeln( '<error>AWS Request ID: ' . $files_result->data['aws_request_id'] . '</error>' );
				$output->writeln( '<error>AWS Error Type: ' . $files_result->data['aws_error_type'] . '</error>' );
				$output->writeln( '<error>AWS Error Code: ' . $files_result->data['aws_error_code'] . '</error>' );
			}

			$output->writeln( '<error>Could not delete snapshot.</error>' );
			return;
		}

		$db_result = Connection::instance()->db->deleteSnapshot( $id );

		if ( Utils\is_error( $db_result ) ) {
			if ( Utils\is_error( $db_result ) && $verbose ) {
				$output->writeln( '<error>DynamoDB delete error:</error>' );
				$output->writeln( '<error>Error Message: ' . $db_result->data['message'] . '</error>' );
				$output->writeln( '<error>AWS Request ID: ' . $db_result->data['aws_request_id'] . '</error>' );
				$output->writeln( '<error>AWS Error Type: ' . $db_result->data['aws_error_type'] . '</error>' );
				$output->writeln( '<error>AWS Error Code: ' . $db_result->data['aws_error_code'] . '</error>' );
			}

			$output->writeln( '<error>Could not delete snapshot.</error>' );
			return;
		}

		$output->writeln( '<info>Snapshot deleted.</info>' );
	}

}
