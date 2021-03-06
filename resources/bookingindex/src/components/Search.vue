<script>
import Vue from 'vue';
import Component from 'vue-class-component';
import debounce from "../helpers/debounce";

@Component({
	props: {
		onSearch: {
			type: Function,
			default: (query, done) => done(),
		},

		placeholder: {
			type: String,
			default: 'Search...',
		},

		busy: {
			type: Boolean,
			default: false,
		},
	},
})
export default class Search extends Vue {

	// Properties
	// =========================================================================

	intBusy = false;

	// Getters
	// =========================================================================

	get isBusy () {
		return this.intBusy || this.busy;
	}

	// Actions
	// =========================================================================

	doSearch () {
		return debounce(e => {
			this.intBusy = true;
			this.$emit('search', e.target.value, () => {
				this.intBusy = false;
			});
		});
	}

	// Render
	// =========================================================================

	render () {
		return (
			<label class={[this.$style.search, 'icon']}>
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
				     viewBox="0 0 18 18">
					<path
						d="M11.6363636 5.81890909C11.6363636 2.60581818 9.03127273 0 5.81818182 0 2.60509091 0 0 2.60581818 0 5.81890909 0 9.03127273 2.60509091 11.6363636 5.81818182 11.6363636 9.03127273 11.6363636 11.6363636 9.03127273 11.6363636 5.81890909zM9.93178182 9.93149091L16.0001455 15.9998545"
						transform="translate(1 1)"
					/>
				</svg>

				<input
					type="search"
					placeholder={this.$props.placeholder}
					autoFocus
					onInput={this.doSearch()}
				/>

				<span
					class={[
						'spinner',
						this.$style.spinner,
						{ [this.$style.hide]: !this.isBusy }
					]}
				/>
			</label>
		);
	}

}
</script>

<style lang="less" module>
	@import "../variables";

	.search {
		position: relative;

		display: block;
		margin: 30px;
		flex-grow: 1;

		svg {
			position: absolute;
			top: 50%;
			left: 13px;

			pointer-events: none;
			transform: translateY(-50%);
		}

		path {
			fill: none;
			stroke: #D9DDE2;
			stroke-linecap: round;
			stroke-linejoin: round;
			stroke-width: 2;
		}

		input {
			display: block;
			width: 100%;
			padding: 10px 0;

			color: @text-color;
			font-size: 14px;
			font-family: @font-family;
			text-indent: 40px;

			appearance: none;
			background: #fff;
			border: 1px solid #D9DDE2;
			border-radius: 3px;

			transition: border-color 0.15s ease;

			&:focus {
				border-color: @primary;
			}
		}

		span {
			position: absolute;
			top: 50%;
			right: 10px;
			transform: translateY(-50%);

			pointer-events: none;
		}
	}

	.spinner {
		transition: opacity 0.15s ease;
		pointer-events: none;

		&.hide {
			opacity: 0;
		}
	}
</style>