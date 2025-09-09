.PHONY: localstackup
localstackup:
	@echo "Starting LocalStack..."
	@docker compose -f docker-compose.yml -f docker-compose.localstack.yml up

.PHONY: localstackdown
localstackdown:
	@echo "Stopping LocalStack..."
	@docker compose -f docker-compose.yml -f docker-compose.localstack.yml down


